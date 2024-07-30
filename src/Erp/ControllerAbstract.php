<?php
declare(strict_types=1);

namespace Simplex\Erp;

//contructor injections
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;
use Simplex\VanillaCookieExtended;

use Psr\Http\Message\ServerRequestInterface;
use Spatie\Image\Image;
use Spatie\Image\Manipulations;

use function Simplex\getInstanceNamespace;
use function Simplex\getInstancePath;

/*
* Extends the ControllerAbstract class adding CRUDL (https://en.wikipedia.org/wiki/Create,_read,_update_and_delete) funcionalities:
* - handling of list, create-form, update-form, delete-form, create, update and delete actions
* - requires route to pass a 'model' parameter, a class with the same name will be looked for in current namespace
*/
abstract class ControllerAbstract extends ControllerWithoutCRUDLAbstract
{
    /**
    * @var array
    * ancestors models passed by route
    */
    protected $ancestors = [];

    /**
    * @var object
    * CRUDL config object
    **/
    protected $CRUDLConfig;
    
    /**
    * @var object
    * current user options, set by the UI and stored into area cookie under subject property
    **/
    protected $subjectCookie;
    
    /**
    * @var int
    * query limit
    **/
    protected $queryLimit = null;
    
    /**
    * @var array
    * list extra fields any field to get into list query in addition to the ones defined into table/view for example:
    *                fields aliases
    *                fields based on runtime variables
    **/
    protected $extraFields = [];
    
    /**
    * Constructor
    * @param ContainerInterface $DIContainer
    * @param ResponseInterface $response
    * @param Environment $templateEngine
    * @param VanillaCookieExtended $cookie
    */
    public function __construct(
        ContainerInterface $DIContainer,
        ResponseInterface $response,
        Environment $templateEngine,
        VanillaCookieExtended $cookie
    )
    {
        parent::__construct($DIContainer, $response, $templateEngine, $cookie);
        //store model
        $this->storeModel();
    }
    
    /**
    * Performs some operations before action execution
    * @param ServerRequestInterface $request
    */
    protected function doBeforeActionExecution(ServerRequestInterface $request)
    {
        //load ERP config
        $this->loadCRUDLConfig();
        //Parent jobs
        parent::doBeforeActionExecution($request);
        //store subject
        $this->storeSubject();
        //store current route subject root
        $this->storeCurrentSubjectRoot();
        //store model
        //$this->storeModel();
        //store ancestors
        if($this->isAuthenticated()) {
          $this->storeAncestors();
        }
        //get cookie stored user options
        $this->subjectCookie = $this->getSubjectCookie();
        //load navigation
        if($this->isAuthenticated()) {
            $this->loadSubjectNavigation();
        }
        //set specific CRUDL template parameters
        $this->setCommonTemplateParameters();
        //process input
        $this->processPresetInputs();
    }
    
    
    /**
     * Builds the route to an action from current route subject root
     * @param string $actionRoutePart: the last part of the route wit action name and optional other parameters (such as primary key value)
     * @return string the built route
     */
    protected function buildRouteToActionFromRoot(string $actionRoutePart, string $baseRoute = null): string
    {
        return sprintf('%s/%s', $baseRoute ?? $this->currentSubjectRoot, $actionRoutePart);
    }

    /**
     * Loads model at runtime
     */
    protected function loadModel($subject)
    {
        $modelClassKey = sprintf('%s-model', $subject);
        //if model class has been defined into subject di-container config file load it
        if($this->DIContainer->has($modelClassKey)) {
            $this->model = $this->DIContainer->get($modelClassKey);
        }
    }
    
    /**
     * Stores ancestors models searching for a ancestor-namespace\Model class
     */
    protected function storeAncestors()
    {
        //loop routeParameters
        foreach ($this->routeParameters as $parameter => $subjectKey) {
            if(substr($parameter, 0, 8) == 'ancestor') {
                //get the route fragment to this ancestor
                preg_match(sprintf('~^([0-9a-zA-Z-_/]*/%s)/~', $subjectKey), $this->currentSubjectRoot, $matches);
                //controller
                $controller = $this->DIContainer->get(sprintf('%s-controller', $subjectKey));
                //model
                $model = $this->DIContainer->get(sprintf('%s-model', $subjectKey));
                $modelConfig = $model->getConfig();
                $modelPrimaryKey = $model->getConfig()->primaryKey;
                //record
                $primaryKey = $this->getAncestorPrimaryKeyFromRoute($subjectKey, $modelConfig);
                $where = [[$modelPrimaryKey, $primaryKey->value]];
                $CRUDLConfig = $controller->getCRUDLConfig();
                if(isset($CRUDLConfig->localized) && $CRUDLConfig->localized) {
                    $where[] = ['language_code', $this->language->{'ISO-639-1'}];
                }
                $record = $model->first($where);
                //suppose the main action to ancestor is list as it should
                $routeBack = sprintf('%s/list', $matches[1]);
                $this->ancestors[$subjectKey] = (object) [
                    'controller' => $controller,
                    'model' => $model,
                    'record' => $record,
                    'baseRoute' => $matches[1],
                    'routeBack' => $routeBack
                ];
            }
        }
    }
    
    /**
     * Loads CRUDL config which is mandatory for ERP and contains informations for the CRUDL interface to be exposed
     */
    protected function getAncestorPrimaryKeyFromRoute($ancestorSubjectKey, $ancestorModelConfig)
    {
        try {
            //primary key field alias from config file
            $ancestorPrimaryKey = $ancestorModelConfig->primaryKeyAlias;
            $primaryKeyValue = $this->routeParameters->$ancestorPrimaryKey;
        } catch (\Exception $e) {
            //primary key field from config file
            $ancestorPrimaryKey = $ancestorModelConfig->primaryKey;
            $primaryKeyValue = $this->routeParameters->$ancestorPrimaryKey;
            try {
            } catch (\Exception $e) {
                try {
                    //primary key automatically built
                    //sometimes tables primary key fields in a schema have all the same name (i.e. 'id') and therefor must be mapped into same route with made-up names in the form subjectKey_id
                    $ancestorPrimaryKey = sprintf('%s_id', $ancestorSubjectKey);
                    $primaryKeyValue = $this->routeParameters->$ancestorPrimaryKey;
                } catch (\Exception $e) {
                    throw new \Exception(sprintf('Controller %s has defined ancestor "%s" into current route but ancestor primary key is not found', getInstanceNamespace($this), $ancestorSubjectKey));
                }
            }
        }
        return (object) [
            'field' => $ancestorPrimaryKey,
            'value' => $primaryKeyValue
        ];
    }
    
    /**
     * Loads CRUDL config which is mandatory for ERP and contains informations for the CRUDL interface to be exposed
     */
    protected function loadCRUDLConfig()
    {
        //config file must be into class-folder/config/crudl.php
        $configPath = sprintf('%s/config/crudl.php', getInstancePath($this));
        //check path
        if(!is_file($configPath)) {
            throw new \Exception(sprintf('configuration CRUDL file \'%s\' for subject %s is not a valid path', $configPath, getInstanceNamespace($this)));
        }
        $config = require($configPath);
        //check that config is an object
        if(!is_object($config)) {
          throw new \Exception(sprintf('CRUD configuration file \'%s\' for controller %s must return an object', $configPath, getInstanceNamespace($this)));
        }
        //check fields property
        if(!isset($config->fields)) {
          throw new \Exception(sprintf('CRUD configuration loaded from file \'%s\' for controller %s must contain a \'fields\' property', $configPath, getInstanceNamespace($this)));
        }
        //list query limit
        if(isset($config->queryLimit) && is_int($config->queryLimit)) {
          $this->queryLimit = $config->queryLimit;
        }
        //store config
        $this->CRUDLConfig = $config;
    }
    
    /**
     * Gests CRUDL config
     */
    public function getCRUDLConfig()
    {
        $this->loadCRUDLConfig();
        return $this->CRUDLConfig;
    }
    
    /**********
    * OPTIONS *
    **********/
    
    /**
     * Gets user options for a subject stored into cookie
     * @param string $subject: defaults to current
     */
    protected function getSubjectCookie($subject = null): object
    {
        $subjectCookie = $this->getAreaCookie()->{($subject ?? $this->subject)} ?? new \stdClass;
        return $subjectCookie;
    }

    /**
     * Sets an subject information to be stored into cookie
     * @param string $key
     * @param mixed $value
     * @param string $subject: defaults to current
     */
    protected function setSubjectCookie(string $key, $value, $subject = null)
    {
        $subjectCookie = $subject ? $this->getSubjectCookie($subject) : $this->subjectCookie;
        $subjectCookie->$key = $value;
        $this->setAreaCookie(($subject ?? $this->subject), $subjectCookie);
        if($subject == null || $subject == $this->subject) {
          $this->subjectCookie = $subjectCookie;
        }
    }

    /**
     * Sets a feedback categorized alert into subject cookie
     * @param string $severity one of Bootstrap alert suffixes (https://getbootstrap.com/docs/4.3/components/alerts)
     * @param object $alert object with alert informations:
     *   ->code: alphanumeric message code to be searched for into template alerts texts container
     *   ->rawMessage: a message (alternative to code)
     *   ->data: an array with any specific error code relevant data (such as involved field names), inserted into message by means of template format filter
     * @param string $subject: defaults to current
     */
    protected function setSubjectAlert(string $severity, object $alert, $subject = null)
    {
        //init messages
        /*if(!isset($this->subjectCookie->alerts)) {
            $this->subjectCookie->alerts = [];
        } else {
            //json_decode turn associative array into objects
            $this->subjectCookie->alerts = (array) $this->subjectCookie->alerts;
        }
        //init context messages
        if(!isset($this->subjectCookie->alerts[$severity])) {
            $this->subjectCookie->alerts[$severity] = [];
        }
        //set message
        $this->subjectCookie->alerts[$severity][] = $alert;
        */
        //get subject cookie
        $subjectCookie = $subject ? $this->getSubjectCookie($subject) : $this->subjectCookie;
        //get alerts
        $alerts = isset($subjectCookie->alerts) ? (array) $subjectCookie->alerts : [];
        //init context messages
        if(!isset($alerts[$severity])) {
            $alerts[$severity] = [];
        }
        //set message
        $alerts[$severity][] = $alert;
        //store into subject cookie
        //$this->setSubjectCookie('alerts', $this->subjectCookie->alerts);
        $this->setSubjectCookie('alerts', $alerts, ($subject ?? $this->subject));
    }

    /**
     * Resests categorized alerts into subject cookie
     */
    protected function resetSubjectAlerts()
    {
        $this->setSubjectCookie('alerts', null);
    }

    /**
     * Sets a feedback categorized alert into subject cookie taking an exception message
     * @param \Exception $exception
     */
    protected function setSubjectAlertFromException(\Exception $exception)
    {
        $this->setSubjectAlert('danger', $exception->getMessage());
    }

    /*************
    * NAVIGATION *
    *************/
    /**
     * Loads subject navigation which is *always* needed for ERP 
     * @param ServerRequestInterface $request: needed to check navigation permissions if subject is not handling current route action
     */
    public function loadSubjectNavigation(ServerRequestInterface $request = null)
    {
        //config file must be into class-folder/config/navigation.php
        $configPath = sprintf('%s/config/navigation.php', getInstancePath($this));
        //check path
        if(!is_file($configPath)) {
            throw new \Exception(sprintf('configuration file \'%s\' for subject %s navigation is not a valid path', $configPath, getInstanceNamespace($this)));
        }
        //load navigation
        $this->loadNavigation($configPath, $request);
    }
    
    /**
    * Redirects after checking if for current action a redirect action has been defined
    * @param string $route
    * @param object $record: in case route action has to be built over a record datas
    */
    protected function redirect(string $route, $record = null)
    {
        $redirectActionKey = $this->subjectConfig->actions[$this->action]->redirectTo ?? $this->getCRUDLConfig()->actions[$this->action]->redirectTo ?? null;
        //if(isset($this->getCRUDLConfig()->actions[$this->action]->redirectTo)) {
        if($redirectActionKey) {
            $isRecordAction = false;
            //$redirectActionKey = $this->getCRUDLConfig()->actions[$this->action]->redirectTo;
            //GET REDIRECT ACTION OBJECT
            //redirect action is global
            if(isset($this->navigations['globalActions'][$redirectActionKey])) {
                $redirectAction = $this->navigations['globalActions'][$redirectActionKey];
            } elseif(isset($this->navigations['recordVisibleActions'][$redirectActionKey]) || isset($this->navigations['recordVisibleActions'][$redirectActionKey])) {
            //redirect action is related to a record
                $isRecordAction = true;
                $redirectAction = isset($this->navigations['recordVisibleActions'][$redirectActionKey]) ? $this->navigations['recordVisibleActions'][$redirectActionKey] : $this->navigations['recordVisibleActions'][$redirectActionKey];    
            }
            //GET DEFINED REDIRECT ACTION ROUTE
            $route = isset($redirectAction->routeFromSubject) ? $this->buildRouteToActionFromRoot($redirectAction->routeFromSubject) : $redirectAction->route;
            //MANAGE RECORD ACTION
            if($isRecordAction) {
                if(!is_object($record)) {
                    throw new \Exception(sprintf("cannot redirect to action %s because a null record has been passed", $redirectActionKey), 1);
                } else {
                    $route = $this->parseRecordActionRoute($route, $record);
                }
            }
        }
        parent::redirect($route);
    }

    /***********
    * TEMPLATE *
    ***********/
    
    
    /**
    * Build common template helpers going up the inheritance chain, used to generate templates cache during translations extraction
    */
    protected function buildTemplateHelpersBack()
    {
        parent::buildTemplateHelpersBack();
        $this->buildCommonTemplateHelpers();
    }
    
    /**
    * Sets common template parameters
    */
    protected function setCommonTemplateParameters()
    {
        $this->setTemplateParameter('_GET', $_GET);
        $this->setTemplateParameter('_POST', $_POST);
        $this->setTemplateParameter('subject', $this->subject);
        $this->setTemplateParameter('userData', $this->getAuthenticatedUserData());
        $this->setTemplateParameter('model', $this->model);
        $this->setTemplateParameter('ancestors', $this->ancestors);
        $this->setTemplateParameter('CRUDLConfig', $this->CRUDLConfig);
        $this->setTemplateParameter('currentNavigationVoice', $this->currentNavigationVoice);
        $this->setTemplateParameter('sideBarClosed', $this->getAreaCookie('sideBarClosed') ?? false);
    }
    
    /**
    * Processes input for rpeset operation (like bulk ones)
    */
    protected function processPresetInputs()
    {
        //POST
        $possiblePostInputsDefinitions = [
            //bulk records actions
            'bulk_action_records_ids' => [
                'filter' => FILTER_VALIDATE_REGEXP,
                'options' => ['regexp' => '/([0-9]{1,}\|?)+/']
            ]
        ];
        $postInput = (object) filter_input_array(INPUT_POST, $possiblePostInputsDefinitions);
        //bulk operations, turn ids string into array
        if(isset($postInput->bulk_action_records_ids)) {
            $_POST['bulk_action_records_ids'] = explode('|', $postInput->bulk_action_records_ids);
        }
    }
    
    /**************
    * LIST ACTION *
    **************/

    /**
     * Gets the recordset for the list
     */
    protected function getList()
    {
        if(isset($this->subjectCookie->sorting) && !empty($this->subjectCookie->sorting)) {
            $sorting = $this->subjectCookie->sorting;
        } elseif($this->model->hasDb && $this->model->hasPositionField) {
            $sorting = [[$this->model->getConfig()->position->field]];
        } elseif(isset($this->getCRUDLConfig()->listOrderBy)) {
            $sorting = $this->getCRUDLConfig()->listOrderBy;
        //ordering by primary key adds slows query
        /*} elseif($this->model->hasDb && $this->model->getConfig()->primaryKey) {
            $sorting = [[$this->model->getConfig()->primaryKey]];*/
        } else {
            $sorting = [];
        }
        $records = $this->model->get(
            $this->buildListWhere(),
            $sorting,
            $this->queryLimit,
            $this->extraFields
        );
        //xx($this->model->sql());
        if($this->model->hasDb && $this->model->hasPositionField) {
            $numRecords = count($records);
            for ($i=0; $i < $numRecords; $i++) {
                //move up?
                $records[$i]->moveUp = $i > 0;
                //move down?
                $records[$i]->moveDown = $i < ($numRecords - 1);
            }
        }
        //totals
        $tableTotals = [];
        foreach ($this->getCRUDLConfig()->fields as $fieldName => $fieldDefinition) {
          if(isset($fieldDefinition->table->total) && $fieldDefinition->table->total) {
            $tableTotals[$fieldName] = 0;
          }
        }
        if(!empty($tableTotals)) {
          foreach ((array) $records as $record) {
            foreach ($tableTotals as $fieldName => $value) {
              $tableTotals[$fieldName] += $record->{$fieldName};
            }
          }
          $this->stp('tableTotals', $tableTotals);
        }
        return $records;
    }
    
    /**
     * Lists records
     */
    protected function list()
    {
        //check list query modifiers
        $this->setListQueryModifiers();
        //get model list
        if(!isset($this->getCRUDLConfig()->queryOnFilter) || !$this->getCRUDLConfig()->queryOnFilter || $this->isFiltered()) {
          $records = $this->getList();
        } else {
          $records = [];
        }
        $this->setTemplateParameter('records', $records);
        if($this->model->hasFs) {
          $this->stp('pathToFolder', $this->model->getPathToFolder());
        }
        //render
        $this->renderTemplate(sprintf(
            '@local/%s/%s/list.%s',
            getInstanceNamespace($this, true),
            TEMPLATES_DEFAULT_FOLDER,
            TEMPLATES_EXTENSION
        ));
    }
    
    /**
     * Checks information passed by list view that modifies model query: sorting, filtering and pagination
     */
    protected function setListQueryModifiers()
    {
        //get input
        $fieldsDefinitions = [
            'modifier' => FILTER_SANITIZE_SPECIAL_CHARS,
            'field' => FILTER_SANITIZE_SPECIAL_CHARS,
            //sorting
            'direction' => [
                'filter' => FILTER_VALIDATE_REGEXP,
                'options' => array('regexp'=>'/asc|ASC|des|DESC/')
            ],
            //filter
            'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
            //custom conditions
            'custom_conditions' => [
                'filter' => FILTER_UNSAFE_RAW,
                'flags' => FILTER_REQUIRE_ARRAY
            ]
        ];
        $input = filter_input_array(INPUT_POST, $fieldsDefinitions);
        if($input) {
          $input = (object) $input;
          switch ($input->modifier) {
            //sorting
            case 'sort':
              if($input->field) {
                $sorting = [
                  [$input->field, strtoupper($input->direction)]
                ];
              } else {
                $sorting = [];
              }
                $this->replaceListSort($sorting);
            break;
            //filter
            case 'filter':
                $filter = html_entity_decode($input->filter);
                $this->replaceListFilter($filter);
            break;
            //custom conditions
            case 'custom_conditions':
              if($input->custom_conditions !== null) {
                $custom_conditions = array_filter(
                  $input->custom_conditions,
                  function($item) {
                    return $item !== null && $item !== '';
                  }
                );
              } else {
                $custom_conditions = null;
              }
              $this->replaceListCustomConditions($custom_conditions);
            break;
          }
        }
    }
    
    /**
    * Replaces current sorting
    * @param array $sorting: associative array of directions indexed by fields
    */
    public function replaceListSort(array $sorting)
    {
        $this->setSubjectCookie('sorting', $sorting);
    }
    
    /**
    * Replaces current filter
    * @param string $filter
    */
    public function replaceListFilter(string $filter)
    {
        $this->setSubjectCookie('filter', $filter);
    }
    
    /**
    * Replaces current where custom conditions, any type of information to be processed by the buildListWhereCustomConditions method
    * @param string $filter
    */
    public function replaceListCustomConditions($customConditions)
    {
        $this->setSubjectCookie('custom_conditions', $customConditions);
    }
    
    /**
    * Set query list limit
    * @param int $limit
    */
    public function setQueryLimit(int $limit)
    {
        $this->queryLimit = $limit;
    }
    
    /**
    * Set query list extra fields
    * @param array $extraFields: any other field to get in addition to the ones defined into table/view for example:
    *                fields aliases
    *                fields based on runtime variables
    */
    public function setListExtraFields(array $extraFields)
    {
        $this->extraFields = $extraFields;
    }
    
    /**
    * Checks whether list is filtered
    * @retrun bool
    */
    protected function isFiltered()
    {
      $subjectCookie = $this->getSubjectCookie();
      return
        (isset($subjectCookie->filter) && $subjectCookie->filter)
        || 
        (
          isset($subjectCookie->custom_conditions)
          &&
          is_object($subjectCookie->custom_conditions)
          &&
          !empty(get_object_vars($subjectCookie->custom_conditions))
          &&
          array_reduce(
            array_values(get_object_vars($subjectCookie->custom_conditions)),
            function($carry, $item) {
              return $carry + ($item ? 1 : 0);
            },
            0
          )
        );
    }
    
    /**
    * Builds list query where based on modifiers
    * @param object $CRUDLConfig to use a different CRUD configuration
    * @return array as accepted by Pixie query builder
    */
    protected function buildListWhere(object $CRUDLConfig = null, string $filterString = ''): array
    {
        $CRUDLConfig = $CRUDLConfig ?? $this->CRUDLConfig;
        $where = [];
        //localized table
        if($this->model->hasDb && isset($CRUDLConfig->localized) && $CRUDLConfig->localized) {
            $where[] = ['language_code', $this->language->{'ISO-639-1'}];
        }
        //filter
        if(!$filterString) {
          $subjectCookie = $this->getSubjectCookie();
          if(isset($subjectCookie->filter) && $subjectCookie->filter) {
            $filterString = $subjectCookie->filter;
          }
          //forget filter
          if((defined('FORGET_ALL_FILTERS') && !isset($CRUDLConfig->forgetFilter) && FORGET_ALL_FILTERS) || (isset($CRUDLConfig->forgetFilter) && $CRUDLConfig->forgetFilter === true )) {
            $this->replaceListFilter('');
          }
        }    
        if($filterString) {
          $filterWhere = $this->buildFilterWhere($CRUDLConfig, $filterString);
          if($this->model->hasDb) {
            $where[] = $filterWhere;
          } elseif($this->model->hasFs) {
            $where = array_merge($where, $filterWhere);
          }
        }
        //parent primary key
        if($this->model->hasDb && !empty($this->ancestors)) {
            $parent = end($this->ancestors)->model;
            $parentConfig = $parent->getConfig();
            //check parent primary key into route
            $ancestorsChain = array_keys($this->ancestors);
            $ancestorSubjectKey = end($ancestorsChain);
            $parentPrimaryKey = $this->getAncestorPrimaryKeyFromRoute($ancestorSubjectKey, $parentConfig);
            $where[] = [$parentPrimaryKey->field, $parentPrimaryKey->value];
        }
        //filesystem masks
        if($this->model->hasFs && isset($this->model->getConfig()->masks)) {
          $where = array_merge_recursive($where, $this->model->getConfig()->masks);
        }
        $this->buildListWhereCustomConditions($where);
        //forget filter
        if((defined('FORGET_ALL_FILTERS') && !isset($CRUDLConfig->forgetFilter) && FORGET_ALL_FILTERS) || (isset($CRUDLConfig->forgetFilter) && $CRUDLConfig->forgetFilter === true )) {
          $this->replaceListCustomConditions('');
        }
        return $where;
    }
    
    /**
    * Builds where conditions on CRUDL filter fields given a search string to be tokenized
    * @param object $CRUDLConfig to use a different CRUD configuration
    * @param string $filterString
    */
    protected function buildFilterWhere($CRUDLConfig, $filterString): array
    {
        $where = [];
        $filterString = html_entity_decode($filterString);
        //clean multiple white spaces
        $filterString = preg_replace('/\h{2,}/iu', ' ', $filterString);    
        //search for "" quotes
        $quotedTokensNumber = preg_match_all('/"([\w\.\'\h?]+)"/iu', $filterString, $quotedTokens);
        if($quotedTokensNumber > 0) {
            $filterString = preg_replace('/"[\w\.\'\h?]+"/iu', '', $filterString);    
        }
        preg_match_all('/[\w\-\.\']+/i', $filterString, $notQuotedTokens);
        $filterTokens = array_merge($quotedTokens[1], $notQuotedTokens[0]);
        //database model
        if($this->model->hasDb) {
          //create a grouped where for the filter
          $filterWhere = [];
          foreach ($filterTokens as $filterToken) {
            $filterToken = $this->model->getQuery()->escapeSingleQuotes(trim($filterToken));
            if(!$filterToken) {
              continue;
            }
            //loop config fields
            $tokenFields = [
              'grouped' => true
            ];
            foreach ((array) $CRUDLConfig->fields as $fieldName => $fieldConfig) {
              if(!isset($fieldConfig->table->filter) || $fieldConfig->table->filter) {
                $tokenFields[] = [
                  'logical' => 'OR',
                  $this->model->rawField(
                    sprintf(
                      'CAST(%1$s%2$s%1$s AS %3$s) %4$s \'%%%5$s%%\'',
                      $this->model->getQuery()->getDriverOption('labelDelimiter'),
                      $fieldName,
                      $this->model->getQuery()->getDriverOption('likeOperatorTextCastDataType'),
                      $this->model->getQuery()->getDriverOption('caseInsensitiveLikeOperator'),
                      $filterToken
                    )
                  )
                ];
              }
            }
            $filterWhere[] = $tokenFields;
          }
          //filter string has a value
          if(count($filterTokens) > 0) {
            //no filter fields defined => query must fail
            if(empty($filterWhere)) {
              $where = [[$this->model->rawField('0'), null]];
            } else {
              $where = array_merge(
                [
                  'grouped' => true,
                  'logical' => 'AND',
                ],
                $filterWhere
              );
            }
          }
        }
        //file system model
        if($this->model->hasFs) {
          foreach($filterTokens as $filterToken) {
            foreach ((array) $CRUDLConfig->fields as $fieldName => $fieldConfig) {
              if(!isset($fieldConfig->table->filter) || $fieldConfig->table->filter) {
                if(!isset($where[$fieldName])) {
                  $where[$fieldName] = [];
                }
                $where[$fieldName][] = sprintf('*%s*', $filterToken);
              }
            }
          }
        }
        return $where;
    }
    
    /**
    * Processes any custom conditions saved into subject cookie and builds the relative list query where
    * to be overridden by children classes
    * @param array $where
    */
    protected function buildListWhereCustomConditions(&$where)
    {
    }
    
    /***************
    * CRUDL ACTIONS *
    ***************/

    /**
     * Gets model record to operate on by route primary key value
     */
    protected function getModelRecordFromRoute()
    {
        //get primary key fields
        $primaryKey = $this->model->getConfig()->primaryKey;
        $where = [[$primaryKey, $this->routeParameters->$primaryKey]];
        $CRUDLConfig = $this->getCRUDLConfig();
        if($this->model->hasDb && isset($CRUDLConfig->localized) && $CRUDLConfig->localized) {
          $where[] = ['language_code', $this->language->{'ISO-639-1'}];
        }
        $record = $this->model->first($where);
        if(!$record) {
            throw new \Exception("Current route is supposed to retrieve a record but record is not found", 1);
        }
        return $record;
    }
    
    /**
     * Gets any data necessary to the save form
     * to be overridden if necessary by derived classes
     */
    protected function getSaveFormData()
    {
        //uploads
        $modelConfig = $this->model->getConfig();
        if(isset($modelConfig->uploads)) {
            //max file size
            $this->setUploadMaxFilesizeTemplateParameters();
        }
    }
    
    /**
     * Insert form
     */
    protected function insertForm()
    {
        //get any necessary data
        $this->getSaveFormData();
        //render
        $this->renderTemplate(sprintf(
            '@local/%s/%s/crudl-form.%s',
            getInstanceNamespace($this, true),
            TEMPLATES_DEFAULT_FOLDER,
            TEMPLATES_EXTENSION
        ));
    }
    
    /**
     * Update form
     */
    protected function updateForm()
    {
        //get model record
        $this->setTemplateParameter('record', $this->getModelRecordFromRoute());
        //get any necessary data
        $this->getSaveFormData();
        //render
        $this->renderTemplate(sprintf(
            '@local/%s/%s/crudl-form.%s',
            getInstanceNamespace($this, true),
            TEMPLATES_DEFAULT_FOLDER,
            TEMPLATES_EXTENSION
        ));
    }
    
    /**
     * Delete form
     */
    protected function deleteForm()
    {
        //get model record
        $this->setTemplateParameter('record', $this->getModelRecordFromRoute());
        //get any necessary data
        $this->getSaveFormData();
        //render
        $this->renderTemplate(sprintf(
            '@local/%s/%s/crudl-form.%s',
            getInstanceNamespace($this, true),
            TEMPLATES_DEFAULT_FOLDER,
            TEMPLATES_EXTENSION
        ));
    }
    
    /**
     * Processes save form input to manipulate fields data before saving
     * this method is void by default, it must be overridden by derived class if necessary
     * It operates over input by reference and can return any type of extra data that will be inserted into the result of getSaveFieldsData
     */
    protected function processSaveFormInput(&$input)
    {
    }
    
    /**
     * Purges input array from primary key value and returns it
     * this method is void by defaulkt, it must be overridden by derived class if necessary
     */
    protected function extractPrimaryKeyValueFromInput(&$input)
    {
        $primaryKeyField = $this->model->getConfig()->primaryKey;
        //deal with different flavours of input
        if(is_array($input)) {
          $primaryKeyValue = $input[$primaryKeyField];
          unset($input[$primaryKeyField]);
        } elseif(is_object($input)) {
          $primaryKeyValue = $input->$primaryKeyField;
          unset($input->$primaryKeyField);
        }
        return $primaryKeyValue;
    }
    
    /**
     * Gets save form input
     * @return object with properties:
     *      ->primaryKeyValue: primary key value
     *      ->saveFieldsValues: array with values of fields to be saved indexed by fields names
     */
    protected function getSaveFieldsData(): object
    {
        //extract filter definition from fields config
        //get fields filters
        $inputFieldsFilters = array_filter(
            //return true if field has a filter definition
            array_map(
                function($fieldConfiguration) {
                    return $fieldConfiguration->inputFilter ?? null;
                },
                $this->CRUDLConfig->fields
            ),
            //keep only fields with a not null filter definition
            function($fieldFilter) {
                return $fieldFilter;
            }
        );
        //deal with localized fields
        if($this->model->hasDb && $this->model->hasLocales()) {
            $modelLocales = $this->model->getConfig()->locales ?? [];
            foreach ($inputFieldsFilters as $fieldName => $inputFilter) {
                //localized field
                if(in_array($fieldName, $modelLocales)) {
                    if(!is_array($inputFilter)) {
                        $inputFilter = ['filter' => $inputFilter];
                    }
                    if(!isset($inputFilter['flags'])) {
                        $inputFilter['flags'] = 0;
                    }
                    $inputFilter['flags'] = FILTER_REQUIRE_ARRAY;
                    $inputFieldsFilters[$fieldName] = $inputFilter;
                }
            }
        }
        if($this->model->hasDb && $this->model->hasPositionField) {
          $modelPosition = $this->model->getConfig()->position;
          $positionField = $modelPosition->field;
          $inputFieldsFilters[$positionField] = FILTER_VALIDATE_INT;
        }
        $input = filter_input_array(INPUT_POST, $inputFieldsFilters);
        //process input
        $inputExtraData = $this->processSaveFormInput($input);
        //position field
        if($this->model->hasDb && $this->model->hasPositionField) {
            $modelPosition = $this->model->getConfig()->position;
            $positionField = $modelPosition->field;
            if(!$input[$positionField]) {
                $contextFields = $modelPosition->contextFields;
                $contextFieldsValues = [];
                foreach ((array) $contextFields as $contextField) {
                    $contextFieldsValues[$contextField] = $input[$contextField];
                }
                $input[$positionField] = $this->model->getNextPosition($contextFieldsValues);
            }
        }
        //separate localized and non localized values
        $inputLocales = [];
        foreach (array_keys((array) $this->languages) as $languageCode) {
            $inputLocales[$languageCode] = [];
        }
        if($this->model->hasDb && $this->model->hasLocales()) {
            foreach ($input as $fieldName => $fieldLocalesValues) {
                if(in_array($fieldName, $modelLocales)) {
                    if(is_array($fieldLocalesValues)) {
                        foreach ($fieldLocalesValues as $languageCode => $languageValue) {
                            $inputLocales[$languageCode][$fieldName] = $languageValue;
                        }
                    }
                    unset($input[$fieldName]);
                }
            }
        }
        //get primary key and purge it from input values
        $primaryKeyValue = $this->extractPrimaryKeyValueFromInput($input);
        //add eventual model upload fields
        $uploadsInput = null;
        if($this->model->hasDb && $this->model->hasUploads()) {
            $uploadsFilters = [];
            foreach ($this->model->getUploadKeys() as $uploadKey) {
                $uploadsFilters[$uploadKey] = [
                    'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                    //'filter' => FILTER_VALIDATE_REGEXP,
                    //'options' => ['regexp'=>'/^([0-9a-zA-z_\-\.\s\p{L}]+\.[1-9a-zA-Z]{3,4}\|?)+$/u']
                ];
            }
            $uploadsInput = (object) filter_input_array(INPUT_POST, $uploadsFilters);
            
        }
        //get input
        return (object) [
            'primaryKeyValue' => $primaryKeyValue,
            'saveFieldsValues' => $input,
            'saveLocalesFieldsValues' => $inputLocales,
            'uploadsValues' => $uploadsInput,
            'extraData' => $inputExtraData
        ];
    }
    
    /**
     * Inserts record
     */
    protected function insert()
    {
        $fieldsData = $this->getSaveFieldsData();
        try {
            //save record
            $primaryKeyValue = $this->model->insert($fieldsData->saveFieldsValues);
            //save locales
            if($this->model->hasDb && $this->model->hasLocales()) {
                $this->model->saveLocales($primaryKeyValue, $fieldsData->saveLocalesFieldsValues);
            }
            //save uploads
            if($this->model->hasDb && $this->model->hasUploads()) {
                $this->model->saveUploadsFiles($primaryKeyValue, $fieldsData->uploadsValues);
            }
            //post save processing
            $saveProcessing = $this->doAfterRecordSave($primaryKeyValue, $fieldsData);
            //redirect
            if(isset($_POST['callingFormRoute'])) {
              $primaryKeyField = $this->model->getConfig()->primaryKey;
              $redirectRoute = sprintf(
                '%s?%s=%s',
                $_POST['callingFormRoute'],
                $primaryKeyField,
                $primaryKeyValue
              );
            } elseif(is_object($saveProcessing) && isset($saveProcessing->redirectRoute)) {
                $redirectRoute = $saveProcessing->redirectRoute;
            } else {
                $redirectRoute = $this->buildRouteToActionFromRoot('list');
            }
            //message
            if(is_object($saveProcessing) && isset($saveProcessing->messageCode)) {
                $messageCode = $saveProcessing->messageCode;
            } else {
                $messageCode = 'save_success';
            }
            if($messageCode) {
              $messageSeverity = $saveProcessing->messageSeverity ?? 'success';
              $this->setSubjectAlert($messageSeverity, (object) ['code' => $messageCode]);
            }
        } catch(\Exception $exception) {
            //if something went wrong and record has been inserted, delete it, because maybe something went wrong with locales or uploads
            if(isset($primaryKeyValue)) {
              $this->model->delete($primaryKeyValue);
            }
            $error = $this->model->handleException($exception);
            $this->setSubjectAlert('danger', $error);
            //$redirectRoute = $this->buildRouteToActionFromRoot('insert-form');
            $redirectRoute = $_SERVER['HTTP_REFERER'];
        }
        //redirect
        $this->redirect($redirectRoute, (object) $fieldsData->saveFieldsValues);
    }
    
    /**
     * Updates record
     */
    protected function update()
    {
        $fieldsData = $this->getSaveFieldsData();
        try {
            //check primary key
            if(!$fieldsData->primaryKeyValue) {
                throw new \Exception("No primary key value", 1);
            }
            //save record
            if(!empty($fieldsData->saveFieldsValues)) {
                $this->model->update($fieldsData->primaryKeyValue, $fieldsData->saveFieldsValues);
            }
            //save locales
            if($this->model->hasDb && $this->model->hasLocales()) {
                $this->model->saveLocales($fieldsData->primaryKeyValue, $fieldsData->saveLocalesFieldsValues);
            }
            //save uploads
            if($this->model->hasDb && $this->model->hasUploads()) {
                $this->model->saveUploadsFiles($fieldsData->primaryKeyValue, $fieldsData->uploadsValues);
            }
            //post save processing
            $saveProcessing = $this->doAfterRecordSave($fieldsData->primaryKeyValue, $fieldsData);
            //redirect
            if(is_object($saveProcessing) && isset($saveProcessing->redirectRoute)) {
                $redirectRoute = $saveProcessing->redirectRoute;
            } else {
                $redirectRoute = $this->buildRouteToActionFromRoot('list');
            }
            //message
            if(is_object($saveProcessing) && isset($saveProcessing->messageCode)) {
                $messageCode = $saveProcessing->messageCode;
            } else {
                $messageCode = 'save_success';
            }
            if($messageCode) {
              $messageSeverity = $saveProcessing->messageSeverity ?? 'success';
              $this->setSubjectAlert($messageSeverity, (object) ['code' => $messageCode]);
            }
        } catch(\Exception $exception) {
            $error = $this->model->handleException($exception);
            //xx($error);
            $this->setSubjectAlert('danger', $error);
            //$redirectRoute = $this->buildRouteToActionFromRoot(sprintf('update-form/%s', $fieldsData->primaryKeyValue));
            $redirectRoute = $_SERVER['HTTP_REFERER'];
        }
        //redirect
        $this->redirect($redirectRoute, (object) $fieldsData->saveFieldsValues);
    }
    
    /**
     * Performs action after record save
     * to be overridden by children classes if necessary
     * @param mixed $primaryKeyValue
     * @param object $fieldsData as returnd by getSaveFieldsData method
     * @return object
     *    ->redirectRoute to override default redirectRoute
     *    ->messageCode to override default message code
     */
    protected function doAfterRecordSave($primaryKeyValue, $fieldsData)
    {
    }
    
    /**
     * Performs action after record deletion
     * to be overridden by children classes if necessary
     * @param mixed $primaryKeyValue
     * @param object $record
     * @return object
     *    ->redirectRoute to override default redirectRoute
     *    ->messageCode to override default message code
     */
    protected function doAfterRecordDelete($primaryKeyValue, object $record)
    {
    }
    
    /**
     * Deletes record
     */
    protected function delete()
    {
        $primaryKeyField = $this->model->getConfig()->primaryKey;
        //$primayKeyFilter = $this->CRUDLConfig->fields[$primaryKeyField]->inputFilter;
        if(is_array($this->CRUDLConfig->fields[$primaryKeyField]->inputFilter)) {
            $primayKeyFilter = $this->CRUDLConfig->fields[$primaryKeyField]->inputFilter['filter'];
            $primayKeyOptions = $this->CRUDLConfig->fields[$primaryKeyField]->inputFilter;
        } else {
            $primayKeyFilter = $this->CRUDLConfig->fields[$primaryKeyField]->inputFilter;
            $primayKeyOptions = 0;
        }
        $primaryKeyValue = filter_input(INPUT_POST, $primaryKeyField, $primayKeyFilter, $primayKeyOptions);
        try {
            //delete record
            $record = $this->model->first([[$primaryKeyField, $primaryKeyValue]]);
            $this->model->delete($primaryKeyValue);
            $saveProcessing = $this->doAfterRecordDelete($primaryKeyValue, $record);
            //redirect
            if(is_object($saveProcessing) && isset($saveProcessing->redirectRoute)) {
                $redirectRoute = $saveProcessing->redirectRoute;
            } else {
                $redirectRoute = $this->buildRouteToActionFromRoot('list');
            }
            //message
            if(is_object($saveProcessing) && isset($saveProcessing->messageCode)) {
                $messageCode = $saveProcessing->messageCode;
            } else {
                $messageCode = 'delete_success';
            }
            if($messageCode) {
              $messageSeverity = $saveProcessing->messageSeverity ?? 'success';
              $this->setSubjectAlert($messageSeverity, (object) ['code' => $messageCode]);
            }
        } catch(\PDOException $exception) {
            $error = $this->model->handleException($exception);
            $this->setSubjectAlert('danger', $error);
            //$redirectRoute = $this->buildRouteToActionFromRoot(sprintf('delete-form/%s', $primaryKeyValue));
            $redirectRoute = $_SERVER['HTTP_REFERER'];
        }
        //redirect
        $this->redirect($redirectRoute);
    }
    
    /**
     * Bulk deletion
     */
    protected function deleteBulk()
    {
        //loop bulk_action_records_ids (automatically exploded into $_POST)
        try {
          foreach ($_POST['bulk_action_records_ids'] as $primaryKeyValue) {
              $this->model->delete($primaryKeyValue);
          }
          $this->setSubjectAlert('success', (object) ['code' => 'delete_bulk_success']);
        } catch(\PDOException $exception) {
            $error = $this->model->handleException($exception);
            $this->setSubjectAlert('danger', $error);
        }
        $redirectRoute = $this->buildRouteToActionFromRoot('list');
        $this->redirect($redirectRoute);
    }
    
    /**
     * Clones record
     * @param int $primaryKeyFieldValue
     * @param string $parentPrimaryKeyField
     * @param int $parentPrimaryKeyFieldValue
     * @return int cloned record id
     */
    public function clone($primaryKeyFieldValue, $parentPrimaryKeyField = null, $parentPrimaryKeyFieldValue = null)
    {
        //load model in case you're cloning from another subject
        $this->loadModel($this->subject);
        //get fields to be marked
        $fieldsToMark = $this->getCRUDLConfig()->clone ?? [];
        $fieldsToUpdate = [];
        //optional change of parent
        if($parentPrimaryKeyFieldValue) {
            $fieldsToUpdate[$parentPrimaryKeyField ] = $parentPrimaryKeyFieldValue;
        }
        $cloned_record_id = current($this->model->clone($primaryKeyFieldValue, $fieldsToMark, $fieldsToUpdate));
        return $cloned_record_id;
    }
    
    /**
     * Bulk cloning
     */
    protected function cloneBulk()
    {
        $fieldsDefinition = [
            'parent_primary_key_field' => FILTER_SANITIZE_SPECIAL_CHARS,
            'parent_id' => FILTER_VALIDATE_INT
            
        ];
        $input = (object) filter_input_array(INPUT_POST, $fieldsDefinition);
        if(($input->parent_primary_key_field && !$input->parent_id) || (!$input->parent_primary_key_field && $input->parent_id)) {
            throw new \Exception('parent_primary_key_field and parent_id fields must be both set or neither');
        }
        //loop selected records ids (automatically exploded into $_POST)
        foreach ($_POST['bulk_action_records_ids'] as $primaryKeyFieldValue) {
            //clone
            $this->clone($primaryKeyFieldValue, $input->parent_primary_key_field, $input->parent_id);
        }
        //redirect
        $redirectRoute = $this->buildRouteToActionFromRoot('list');
        $this->redirect($redirectRoute);
    }
    
    
    /**********
     * UPLOAD *
     *********/
    
    /**
     * Uploads a file posted through the upload field of a form
     */
    protected function upload()
    {
        //get upload name cleaning file input name from the -upload suffix
        $inputName = array_keys($_FILES)[0];
        $uploadKey = str_replace('-upload', '', $inputName);
        $fileName = $_FILES[$inputName]['name'];
        //add timestamp to file name to avoid over writing?
        if($this->model->hasUniqueUploads) {
          $nameInfo = (object) pathinfo($fileName);
          $fileName = sprintf(
              '%s_%s.%s',
              $nameInfo->filename,
              time(),
              $nameInfo->extension
          );
        }
        $this->uploadCore($uploadKey, $fileName, $_FILES[$inputName]['tmp_name']);
    }
    
    /**
     * Uploads a file
     * @param string $uploadKey
     * @param string $fileName
     * @param string $fileSourcePath
     * @param bool $isUploadedFile: whether file is uploaded or already stored into filesystem
     * @param bool $outputError: whether to outpu erro in json format
     */
    public function uploadCore(string $uploadKey, string $fileName, string $fileSourcePath, bool $isUploadedFile = true, bool $outputError = true)
    {
        //return object
        $return = (object) [
            'fileName' => $fileName
        ];
        $errors = [];
        //check uploads folder
        $uploadsFolder = $this->model->getUploadsFolder();
        if(!is_dir($uploadsFolder)) {
            //create upload store folder
            mkdir($uploadsFolder, 0755, true);
        }
        //check uploads configuration
        $modelConfig = $this->model->getConfig();
        if(!isset($modelConfig->uploads)) {
            $errors[] = sprintf('Model %s have no uploads configuration', $this->subject);
        //check upload configuration
        }elseif(!isset($modelConfig->uploads[$uploadKey]) || empty($modelConfig->uploads[$uploadKey])) {
            $errors[] = sprintf('Upload %s is not set into model %s configuration', $uploadKey, $this->subject);
        } else {
            //check upload folder
            $uploadFolder = $this->model->getUploadFolder($uploadKey);
            if(!is_dir($uploadFolder)) {
                //create upload store folder
                mkdir($uploadFolder, 0755, true);
            }
            //move uploaded file to upload folder so that each output can access it (because move_uploaded_file deletes file)
            $uploadFilePath = sprintf('%s/%s', $uploadFolder, $fileName);
            if($isUploadedFile) {
                move_uploaded_file($fileSourcePath, $uploadFilePath);
            } else {
                rename($fileSourcePath, $uploadFilePath);
            }
            //loop outputs
            foreach ($modelConfig->uploads[$uploadKey] as $outputKey => $output) {
                //check output folder
                $outputFolder = $this->model->getOutputFolder($uploadKey, $outputKey);
                if(!is_dir($outputFolder)) {
                    //create output store folder
                    mkdir($outputFolder, 0755, true);
                    //create default .gitignore file
                    $fp = fopen(sprintf('%s/.gitignore', $outputFolder), 'w');
                    fwrite($fp, sprintf('# Ignore everything in this directory%1$s*%1$s!.gitignore', PHP_EOL));
                    fclose($fp);
                }
                //copy original uploaded file
                $outputFilePath = $this->model->getOutputFilePath($uploadKey, $outputKey, $fileName);
                copy($uploadFilePath, $outputFilePath);
                //handler
                if(isset($output->handler)) {
                    $parameters = array_merge([$outputFilePath], $output->parameters ?? []);
                    try {
                      if($output->handler[0] == 'this') {
                        call_user_func_array([$this, $output->handler[1]], $parameters);
                      } else {
                        call_user_func_array($output->handler, $parameters);
                      }
                    } catch(\Exception $exception) {
                        $errors[] = $exception->getMessage();
                    }
                }
            }
            //delete original uploaded file
            unlink($uploadFilePath);
        }
        //store error
        $return->error = implode('<br>', $errors);
        if($outputError) {
            echo json_encode($return);
        }
    }
    
    /**
     * Resizes an image proprtionally to fit into a box of given width and height, keeping original ratio but stretching if necessary
     * @param string $path
     * @param int $width
     * @param int $height
     */
    protected static function resizeImage($path, $width, $height)
    {
        Image::load($path)
           ->width($width)
           ->height($height)
           ->save();
    }
    
    /**
     * Resizes an image to fit into a box of given width and height, stretching and changing of the original ratio depend on $cropMethodConstantName (default no crop nor distortion)
     * @param string $path
     * @param int $width
     * @param int $height
     * @param int $fitMethodConstantName: name of constant of the Spatie\Image\Enums\Fit class: Fit::Contain, Fit::Max, Fit::Fill, Fit::Stretch, Fit::Crop (see https://spatie.be/docs/image/v3/image-manipulations/resizing-images#content-fit)
     */
    protected static function fitImage($path, $width, $height, $fitMethodConstantName = 'Fit::Contain')
    {
        Image::load($path)
           ->fit(constant('Spatie\Image\Enums\\' . $fitMethodConstantName), $width, $height)
           ->save();
    }
    
    /**
     * Resizes (proprtionally to fit into a box of given width and ratio) and crops an image
     * @param string $path
     * @param int $width
     * @param int $height
     * @param string $cropMethodConstantName: name of constant of the Spatie\Image\Enums\CropPosition class: CropPosition::TopLeft, CropPosition::Top, CropPosition::TopRight, CropPosition::Left, CropPosition::Center, CropPosition::Right, CropPosition::BottomLeft, CropPosition::Bottom, CropPosition::BottomRight (see https://spatie.be/docs/image/v3/image-manipulations/resizing-images#content-crop)
     */
    protected static function resizeAndCropImage($path, $width, $height, $cropMethodConstantName)
    {
      Image::load($path)
        ->width($width)
        ->height($height)
        ->crop($width, $height, constant('Spatie\Image\Enums\\' . $cropMethodConstantName))
        ->save();
    }
    
    /**
     * Crops and resizes (proprtionally to fit into a box of given width and ratio) an image
     * @param string $path
     * @param int $width
     * @param int $height
     * @param string $cropMethodConstantName: name of constant of the Spatie\Image\Enums\CropPosition class: CropPosition::TopLeft, CropPosition::Top, CropPosition::TopRight, CropPosition::Left, CropPosition::Center, CropPosition::Right, CropPosition::BottomLeft, CropPosition::Bottom, CropPosition::BottomRight (see https://spatie.be/docs/image/v3/image-manipulations/resizing-images#content-crop)
     */
    protected static function cropAndResizeImage($path, $width, $height, $cropMethodConstantName)
    {
      Image::load($path)
        ->crop($width, $height, constant('Spatie\Image\Enums\\' . $cropMethodConstantName))
        ->width($width)
        ->height($height)
        ->save();
    }
    
    /**********************************
    * RECORD POSITION REALTED ACTIONS *
    **********************************/
    
    /**
     * Moves record down
     */
    protected function moveRecordDown()
    {
        $this->moveRecord('down');
    }
    
    /**
     * Moves record up
     */
    protected function moveRecordUp()
    {
        $this->moveRecord('up');
    }
    
    /**
     * Moves record up/down
     * @param string $direction: up | down
     */
    protected function moveRecord($direction)
    {
        $this->model->changeRecordPosition($this->routeParameters->{$this->model->getConfig()->primaryKey}, $direction);
        $this->redirect($this->buildRouteToActionFromRoot('list'));
    }
    
    /***************
    * AUTOCOMPLETE *
    ***************/
    
    /**
     * Gets and outputs the recordset for autocomplete
     * @param array $orderBy: indexed array, each element is an array like request by Simplex\PixieExtended sortBy; if not provided is searched into $this->CRUDLConfig->autocomplete->orderBy
     * @param array $labelFields: the name of one field or an array of fields names and strings to be joined to form label; if not provided is searched into $this->CRUDLConfig->autocomplete->labelFields
     * @param array $extraWhere: extyra where fields
     */
    protected function autocomplete(array $orderBy = null, array $labelFields = null, array $extraWhere = [])
    {
      $where = $this->buildListWhere(null, $_GET['term'] ?? '');
      $where = array_merge($where, $extraWhere);
      $records = $this->model->get(
          $where,
          $orderBy ?? $this->CRUDLConfig->autocomplete->orderBy ?? []
      );
      $valueField = null;
      if($this->model->hasDb) {
        $valueField = $this->model->getconfig()->primaryKey;
      } elseif($this->model->hasFs) {
        $valueField = '';
      }
      $records = $this->processRecordsetForInput(
        $valueField,
        $labelFields ?? $this->CRUDLConfig->autocomplete->labelFields ?? [],
        $records,
        null,
        'id',
        'text'
      );
      $this->output('text/json', json_encode($records));
    }
    
    /***********
    * CALENDAR *
    ***********/
    
    /**
     * Gets calendar events for feed; it relies on $this->queryParameters:
     * 'start': first day of time span  and 'end'
     * 'end': last day of time span + 1 day
     * NOTE: MUST be overridden by child class
     * @param \DateTime $start
     * @param \DateTime $end
     * @return array of objects with properties as described into https://fullcalendar.io/docs/event-object
     */
    protected function getCalendarEvents(\DateTime $start, \DateTime $end): array
    {
      throw new \Exception(sprintf('current class "%s" must implement method "%s" to get Calendar Events Feed', static::class, 'getCalendarEvents'));
    }
    
    /**
     * Gets events for calendar
     * called by a get-calendar-events-feed route action
     * NOTE: final class must provide a protected getCalendarEvents
     */
    protected function getCalendarEventsFeed()
    {
        //build start and end objects
        $start = \DateTime::createFromFormat('Y-m-d\TG:i:sT', $this->queryParameters->start);
        $end = \DateTime::createFromFormat('Y-m-d\TG:i:sT', $this->queryParameters->end);
        //get events
        $events = $this->getCalendarEvents($start, $end);
        //output
        $this->output('text/json', json_encode($events));
    }
}
