<?php
declare(strict_types=1);

namespace Simplex\Controller;
//parent
use Simplex\Controller\ControllerAbstract;
//contructor injections
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;
use Simplex\VanillaCookieExtended;
//other classes and functions
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function Simplex\slugToPSR1Name;
use function Simplex\PSR1NameToSlug;
use function Simplex\getInstancePath;
use function Simplex\buildLocaleRoute;

/*
* In this context controller means a class that:
* - is associated as a handler to a route
* - gets invoked by the request handler middleware, so must be callable, so must have an __invoke magic method
* - receives the request object ($this->request) and the parameters defined for the route ($this->routeParameters)
* - if a route parameter named 'action' exists:
*       - the predefined __invoke method tries to call a public or protected method of the concrete class named after that parameter value
*       - the method name SHOULD be camelcased, so the parameter value SHOULD have hypens (-) to separate words
* - otherwise the __invoke method must be override by concrete derived class
*/
abstract class ControllerWithTemplateAbstract extends ControllerAbstract
{
    /**
    * @var Environment
    * Template engine
    */
    protected $template;
    
    /**
    * @var VanillaCookieExtended
    * cookies manager
    */
    protected $cookie;

    /**
    * @var array
    * parameters to be passed to template engine
    */
    protected $templateParameters = [];

    /**
    * @var array
    * navigations
    */
    protected $navigations = [];
    
    /**
    * @var array
    * currently selected voice key into navigations, indexed by navigation since each route can be set into different anvigations with different keys
    */
    protected $currentNavigationVoice = [];
    
    /**
    * @var object
    * labels containers
    */
    protected $labels;
    
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
        parent::__construct($DIContainer, $response);
        $this->template = $templateEngine;
        $this->cookie = $cookie;
        $this->labels = (object) [
            'actions' => (object) [],
            'alerts' => (object) [],
            'table' => (object) []
        ];
        //reset translations sources
        //$_SESSION[TRANSLATIONS_SOURCE_KEY] = [];
    }

    /**
    * Performs some operations before action execution
    * @param ServerRequestInterface $request
    */
    protected function doBeforeActionExecution(ServerRequestInterface $request)
    {
        //parent jobs
        parent::doBeforeActionExecution($request);
        //build common and local template helpers
        $this->buildCommonTemplateHelpers();
        //set common template parameters
        $this->setCommonTemplateParameters();
    }

    /***********
    * TEMPLATE *
    ***********/

    /**
    * pass a parameter to the template angine
    * @param string $parameterName
    * @param mixed $parameterValue
    */
    protected function setTemplateParameter(string $parameterName, $parameterValue)
    {
        $this->templateParameters[$parameterName] = $parameterValue;
    }

    /**
    * short alias for the - much used - setTemplateParameter method
    * @param string $parameterName
    * @param mixed $parameterValue
    */
    protected function stp(string $parameterName, $parameterValue)
    {
        $this->setTemplateParameter($parameterName, $parameterValue);
    }

    /**
    * gets a parameter from the template angine
    * @param string $parameterName
    */
    protected function getTemplateParameter(string $parameterName)
    {
        return $this->templateParameters[$parameterName] ?? null;
    }

    /**
    * Turns a local path from root into an URL prepending current scheme and domain
    * @param string $path
    */
    protected function turnPathToUrl(string $path): string
    {
        return sprintf(
            '%s://%s%s',
            $_SERVER['REQUEST_SCHEME'],
            $_SERVER['HTTP_HOST'],
            $path
        );
    }
    
    /**
    * Turns a local path from root into an URL prepending current scheme and domain
    * @param string $path
    */
    protected function relativePathToRoot(): string
    {
        return str_repeat(
            '../',
            count(explode('/', $_SERVER['REQUEST_URI'])) - 1
        );
    }
    
    /**
    * Gets a route definition by key and language
    * @param string $routeKey
    * @param string $languageCode
    */
    protected function getRouteDefinition(string $routeKey, string $languageCode)
    {
      $completeKey = sprintf('%s_%s', $routeKey, $languageCode);
      foreach((array) $this->routeParameters->routesDefinitions as $routeDefinition) {
        if(isset($routeDefinition['key']) && $routeDefinition['key'] == $completeKey) {
          return $routeDefinition;
        }
      }
    }
    
    /**
    * Builds a localized route
    * @param string $routeKey
    * @param array $multipleTokensKeys: in case some token has multiple possible values the key to be used, in the order they appear inside route definition
    * @param string $languageCode
    * @return string the route
    */
    protected function buildLocaleRoute(string $routeKey, array $multipleTokensKeys = [], string $languageCode = null): string
    {
      //set language
      if(!$languageCode) {
        $languageCode = $this->language->{'ISO-639-1'};
      }
      $routeDefinition = $this->getRouteDefinition($routeKey, $languageCode);
      $language = $this->languages->$languageCode;
      //compare to page selected language
      $changeLanguage = $languageCode != $this->language->{'ISO-639-1'};
      $route = buildLocaleRoute('route', $language, $routeDefinition['handler'][1]['locale'], $multipleTokensKeys);
      if($changeLanguage) {
        $languageIETF = sprintf('%s_%s', $this->language->{'ISO-639-1'}, $this->language->{'ISO-3166-1-2'});
        setlocale(LC_ALL, sprintf('%s.utf8', $languageIETF));
      }
      return $route;
    }
    
    /**
    * Gets a label by category and (nested) keys
    * first parameter is category, others are nested keys
    * @param string $label translation in current language
    */
    protected function getLabel()
    {
      $arguments = func_get_args();
      if(count($arguments) == 1 && is_array($arguments)) {
          $arguments = $arguments[0];
      }
      $category = array_shift($arguments);
      $result = $this->labels->$category;
      foreach($arguments as $key) {
          //$result = isset($result->$key) ? (is_array($result->$key) ? (object) $result->$key : $result->$key) : sprintf('<span class="alert alert-danger"><b>LABEL NOT FOUND</b>: %s.%s</span>', $category, implode('.', $arguments));
          $result = isset($result->$key) ? (is_array($result->$key) ? (object) $result->$key : $result->$key) : null;
      }
      return $result;
    }

    /**
    * Gets a label key by category, (nested) keys and slugged translation
    * first parameter is category, last is slugged translation, others are nested keys
    * @param string $label translation in current language
    */
    protected function getLabelKeyBySlug()
    {
      $arguments = func_get_args();
      if(count($arguments) == 1 && is_array($arguments)) {
          $arguments = $arguments[0];
      }
      $category = array_shift($arguments);
      $slug = array_pop($arguments);
      $result = $this->labels->$category;
      $slugifier = $this->DIContainer->get('slugifier');
      foreach($arguments as $key) {
          //$result = isset($result->$key) ? (is_array($result->$key) ? (object) $result->$key : $result->$key) : sprintf('<span class="alert alert-danger"><b>LABEL NOT FOUND</b>: %s.%s</span>', $category, implode('.', $arguments));
          $result = isset($result->$key) ? (is_array($result->$key) ? (object) $result->$key : $result->$key) : null;
      }
      foreach((array) $result as $translationKey => $translation) {
        if($slugifier->slugify($translation) == $slug) {
          return $translationKey;
        }
      }
    }

    /**
    * Builds common template helpers
    * NOTE: do not change visibility
    */
    private function buildCommonTemplateHelpers()
    {
        /********
        * DEBUG *
        ********/
        //dumps var in development environment
        $this->addTemplateFunction(
            'dump',
            function($var, $expand = false, $force = false){
                x($var, $expand, $force);
            },
            ['is_safe' => ['html']]
        );
        /************
        * VARIABLES *
        ************/
        //cast to int
        $this->addTemplateFunction('int', function($value) {
            return intval($value);
        });
        //changes an array or object property
        $this->addTemplateFunction('updateProperty', function($input, $property, $value) {
            if(is_array($input)) {
                $input[$property] = $value;
            } elseif (is_object($input)) {
                $input->$property = $value;
            }
            return $input;
        });
        //deletes an array or object property
        $this->addTemplateFunction('deleteProperty', function($input, $property) {
            if(is_array($input)) {
                unset($input[$property]);
            } elseif (is_object($input)) {
                unset($input->$property);
            }
            return $input;
        });
        //casts object to array (to iterate)
        $this->addTemplateFilter('objectToArray', function($object): array{
            return (array) $object;
        });
        //wrapper for vsprintf(), formats a string with an array of arguments
        $this->addTemplateFilter('formatArray', function(string $format, $args): string{
            if(is_array($args) && !empty($args)) {
                return vsprintf($format, $args);
            } else {
                return $format;
            }
        });
        //outputs a variable to be used in javascript context
        //to pass  ablock of javascript code (such as an anonymous function) define in Twig template the variable as an object with a "raw" property set to true and a "value" property set to the javascript block of code
        $this->addTemplateFilter('varToJs', function($var) {
            if(is_array($var) && isset($var['raw']) && $var['raw']) {
              return $var['value'];
            } else {
              return json_encode($var);
            }
        },
        ['is_safe' => ['html']]);
        //convert metric byte units
        //$fromUnit must be in short notation and can refer to metric system (kB, MB, GB, TB, PB, …) or binary (KiB, MiB, GiB, TiB, PiB, …)
        //NOTE: if no value is provided for $fromUnit the unit is bytes
        $this->addTemplateFunction('convertByteUnit', function(float $value, string $fromUnit = '', string $toUnit = ''): string{
            return \ByteUnits\parse(sprintf('%s%s', $value, $fromUnit))->format($toUnit, ' ');
        });
        //ucfirst wrapper
        $this->addTemplateFilter('ucfirst', function(string $string): string{
            return ucfirst($string);
        });
        //ucwords wrapper
        $this->addTemplateFilter('ucwords', function(string $string, string $separators = " \t\r\n\f\v"): string{
            return ucwords($string, $separators);
        });
        //html_entity_decode wrapper
        $this->addTemplateFilter('htmlEntityDecode', function(string $string, int $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, $encoding = null): string{
            return html_entity_decode($string, $flags, $encoding);
        });
        //gets an instance from container 
        $this->addTemplateFunction('getInstanceFromDIContainer', function(string $key) {
            return $this->DIContainer->get($key);
        });
        /********
        * DATES *
        ********/
        /* formats a date with locale awareness
        */
        $this->addTemplateFunction('dateLocale', function(string $date, string $format = null): string {
            $date = \DateTime::createFromFormat('Y-m-d', $date);
            if(!$format) {
              return $date->format($this->language->dateFormat->PHP);
            } else {
              /*$timestamp = (int) $date->format('U');
              return strftime($format, $timestamp);*/
              $fmt = datefmt_create(
                sprintf('%s_%s', $this->language->{'ISO-639-1'}, $this->language->{'ISO-3166-1-2'}),
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::FULL,
                null,
                null,
                $format
              );
              return datefmt_format($fmt, $date);
            }
        });
        /* formats a date with locale awareness
        */
        $this->addTemplateFunction('datetimeLocale', function(string $date, string $format = null) {
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
            if(!$format) {
                return $date->format($this->language->dateTimeFormat->PHP);
            } else {
                /*$timestamp = (int) $date->format('U');
                return strftime($format, $timestamp);*/
                $fmt = datefmt_create(
                sprintf('%s_%s', $this->language->{'ISO-639-1'}, $this->language->{'ISO-3166-1-2'}),
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::FULL,
                null,
                null,
                $format
              );
              return datefmt_format($fmt, $date);
            }
        });
        $this->addTemplateFunction(
            'secondsToTime',
            function ($seconds, $displaySeconds = false) {
                //secondsToTime method is declared into \Traits\Dates which is not always available 
                //return $this->secondsToTime((int) $seconds, $displaySeconds);
                $zero    = new \DateTime("@0");
                $offset  = new \DateTime("@$seconds");
                $diff    = $zero->diff($offset);
                $format = '%02d:%02d';
                $timeSeconds = null;
                if($displaySeconds) {
                  $format .= ':%02d';
                  $timeSeconds = $diff->s;
                }
                return sprintf($format, $diff->days * 24 + $diff->h, $diff->i, $timeSeconds);
            }
        );
        /********
        * PATHS *
        ********/
        //Turns a word in slug notation (route part) into a form as defined by PSR1 standard (https://www.php-fig.org/psr/psr-1/) for class names, method names and such
        $this->addTemplateFunction('slugToPSR1Name', function(string $slug, string $type): string{
            return slugToPSR1Name($slug, $type);
        });
        //gets current request URI
        $this->addTemplateFunction('getUri', function(): string{
            return $this->request->getUri()->getPath();
        });
        //turns a path from root to an URL prepending current scheme and domain
        $this->addTemplateFunction('turnPathToUrl', function(string $path): string{
            return $this->turnPathToUrl($path);
        });
        //checks hether a file path is valid (is_file wrapper)
        $this->addTemplateFunction('isFile', function(string $path): bool{
            //remove trailing slash
            if(strpos($path, '/') === 0) {
                $path = substr($path, 1);
            }
            return is_file($path);
        });
        //wrapper for the pathinfo function
        $this->addTemplateFunction('pathinfo', function(string $path, string $option = '') {
            if($option) {
                return pathinfo($path, constant($option));
            } else {
                return pathinfo($path);
            }
        });
        //returns path to yarn packages asset
        $this->addTemplateFilter('pathToShareAsset', function(string $path){
            return sprintf('/%s/%s', PUBLIC_SHARE_DIR, $path);
        });
        //returns path to yarn packages asset
        $this->addTemplateFilter('pathToLocalAsset', function(string $path){
            return sprintf('/%s/%s', PUBLIC_LOCAL_DIR, $path);
        });
        //returns path to yarn packages asset
        $this->addTemplateFilter('pathToNpmAsset', function(string $path){
            return sprintf('/%s/node_modules/%s', PUBLIC_SHARE_DIR, $path);
        });
        //returns relative path to site root according to current route
        $this->addTemplateFunction('relativePathToRoot', function(){
            return $this->relativePathToRoot();
        });
        //checks whether a given path is the requested URI path
        $this->addTemplateFilter('isNavigationRouteCurrentRoute', function($path){
            return $this->isNavigationRouteCurrentRoute($path);
        });
        //gets path to a local controller or model instance template
        $this->addTemplateFunction('getInstanceTemplate', function($instance, $templateName){
            if($instance == null) {
                $instance = $this;
            }
            return sprintf(
                '@local/%s/%s/%s',
                substr(getInstancePath($instance), strlen(PRIVATE_LOCAL_DIR) + 1),
                TEMPLATES_DEFAULT_FOLDER,
                $templateName
            );
        });
        //builds path to an uploaded file output
        $this->addTemplateFunction(
            'getPublicOutputFilePath',
            function($uploadKey, $outputKey, $fileName, $modelName = null, $getAsUrl = false){
                if($fileName) {
                    $model = $modelName ? $this->$modelName : $this->model;
                    return $model->getPublicOutputFilePath($uploadKey, $outputKey, $fileName);
                }
            }
        );
        //builds path to an uploaded file output
        $this->addTemplateFunction(
            'buildOutputFileFileInfo',
            function($uploadKey, $outputKey, $fileName, $modelName = null, $getAsUrl = false){
                if($fileName) {
                    $model = $modelName ? $this->$modelName : $this->model;
                    $path = $model->getPublicOutputFilePath($uploadKey, $outputKey, $fileName);
                    return new \SplFileInfo($path);
                }
            }
        );
        //clean uploaded file name from timestamp
        $this->addTemplateFilter(
          'cleanOutputFileName',
          function(string $fileName){
            $cleanedFileName = preg_replace(
              '/_[0-9]+(\.[a-zA-z]{2,4})$/',
              '$1',
              $fileName
            );
            return $cleanedFileName;
          }
        );
        //processes a template for a file upload preview
        $this->addTemplateFunction(
            'formatUploadPreviewTemplate',
            function($uploadKey, $fileName, $previewTemplate, $modelName = null){
                //file name
                $previewTemplate = preg_replace(
                    '/@name/',
                    sprintf('%s', $fileName),
                    $previewTemplate
                );
                //get field output keys
                $model = $modelName ? $this->$modelName : $this->model;
                foreach ($model->getUploadKeyOutputs($uploadKey) as $outputKey) {
                    $previewTemplate = preg_replace(
                        sprintf('/@%s/', $outputKey),
                        sprintf('%s', $model->getPublicOutputFilePath($uploadKey, $outputKey, $fileName)),
                        $previewTemplate
                    );
                }
                return $previewTemplate;
            },
            ['is_safe' => ['html']]
        );
        /*********
        * LOCALE *
        *********/
        /* gets currently selected language locale
        * @param string $key: a specific property key of the language object to be returned
        */
        $this->addTemplateFunction('getLanguage', function(string $key = null){
            if($key) {
                return $this->language->$key;
            } else {
                return $this->language;
            }
        });
        
        /* gets a record field value according to current language
        * @param array $field: localized field values
        * @param array $defaultLanguageCode: ISO-639-1 language code to default translation in case there is no value for current language
        */
        $this->addTemplateFunction('getLocaleRecordValue', function(array $field = null, string $defaultLanguageCode = ''){
            if(!$defaultLanguageCode) {
              return $field[$this->language->{'ISO-639-1'}] ?? null;
            } else {
              return $field[$this->language->{'ISO-639-1'}] ? $field[$this->language->{'ISO-639-1'}] : ($field[$defaultLanguageCode] ?? null);
            }
        });
        
        /* alias of getLocaleRecordValue
        * @param string $key: a specific property key of the language object to be returned
        */
        $this->addTemplateFunction('locale', function(array $field = null){
            return $field[$this->language->{'ISO-639-1'}] ?? null;
        });
        
        /* Build a route locale aware from an array o tokens
        * @param string $routeKey: as set into route definition property 'locale'->key
        * @param array $multipleTokensKeys: in case some token has multiple possible values the key to be used, in the order they appear inside route definition
        * @param string $languageCode
        * @return string the route
        */
        $this->addTemplateFunction('buildLocaleRoute', function(string $routeKey, array $multipleTokensKeys = [], string $languageCode = null){
          return $this->buildLocaleRoute($routeKey, $multipleTokensKeys, $languageCode);
        });
        
        /*******
        * FORM *
        *******/
        //parses the config object for a form field that uses templates/form/macros 
        $this->addTemplateFunction('parseFieldConfig', function(array $config){
            //turn into objects
            $config = (object) $config;
            $config->validation = isset($config->validation) ? (object) $config->validation : null;
            //check mandatory attributes
            $mandatoryAttributes = ['name'];
            $mandatoryMissing = [];
            foreach($mandatoryAttributes as $attribute) {
                if(!isset($config->$attribute)) {
                    $mandatoryMissing[] = $attribute;
                }
            }
            if(!empty($mandatoryMissing)) {
                $config->validation->isInvalid = true;
                $config->validation->invalidMessage = sprintf('DEV ALERT: this field misses mandatory attribute(s) %s', implode(', ', $mandatoryMissing));
            }
            //return config
            return $config;
        });
        //formats an id to be used by javascript
        $this->addTemplateFilter('formatIdforJs', function(string $id): string {
            return str_replace(['[',']'], '_', $id);
        });
        //clears regexp to be used as form validation pattern
        $this->addTemplateFilter('cleanRegexp', function(string $regexp): string {
            if(substr($regexp, 0, 1) == substr($regexp, -1)) {
                return substr($regexp, 1, strlen($regexp) - 2);
            } else {
                return $regexp;
            }
        });
        /*********
        * LABELS *
        *********/
        //sets a label by category and key
        $this->addTemplateFunction(
            'setLabel',
            function(string $category, string $key, $value): object {
                $this->labels->$category->$key = $value;
                return $this->labels;
            }
        );
        //sets a group of labels of one category at once
        $this->addTemplateFunction(
            'setLabels',
            function(string $category, array $labels): object {
                $this->labels->$category = (object) array_merge((array) (isset($this->labels->$category) ? $this->labels->$category : []), $labels);
                return $this->labels;
            }
        );
        //gets a label by categories and key path
        $this->addTemplateFunction(
            'getLabel',
            /**
            * first parameter is category, others are nested keys
            **/
            function(...$arguments) {
                return $this->getLabel(...$arguments);
            }
        );
        //gets a label by an array containing categories and key path
        $this->addTemplateFunction(
            'getLabelArray',
            /**
            * first parameter is category, others are nested keys
            **/
            function($arguments) {
                return $this->getLabel(...$arguments);
            }
        );
        //Gets a label key by category, (nested) keys and slugged translation
        $this->addTemplateFunction(
            'getLabelKeyBySlug',
            /** 
            * first parameter is category, last is slugged translation, others are nested keys
            **/
            function(...$arguments) {
                return $this->getLabelKeyBySlug(...$arguments);
            }
        );
        //Gets a label by category, (nested) keys and slugged translation
        $this->addTemplateFunction(
            'getLabelBySlug',
            /** 
            * first parameter is category, last is slugged translation, others are nested keys
            **/
            function(...$arguments) {
                $arguments = func_get_args();
                $labelKey = $this->getLabelKeyBySlug(...$arguments);
                array_pop($arguments);
                array_push($arguments, $labelKey);
                return $this->getLabel(...$arguments);
            }
        );
        /********
        * USERS *
        ********/
        //checks whether user is authenticated
        $this->addTemplateFunction('isAuthenticated', function(){
            return $this->isAuthenticated();
        });
        //checks a user permission
        $this->addTemplateFunction('checkPermission', function(string $permission){
            return $this->checkPermission($permission);
        });
        //checks a user permission amongst a list
        $this->addTemplateFunction('checkAtLeastOnePermission', function(array $permissions){
            return $this->checkAtLeastOnePermission($permissions);
        });
        /********
        * EMAIL *
        ********/
        //obfuscate an email address
        $this->addTemplateFunction('obfuscateEmail', function(string $email){
            if(!defined('MAIL_AT_REPLACEMENT') || !defined('MAIL_DOT_REPLACEMENT')) {
                return 'for mail obfuscation to be used, MAIL_AT_REPLACEMENT and MAIL_DOT_REPLACEMENT constants must be defined';
            } else {
                return str_replace(
                    ['@', '.'],
                    [MAIL_AT_REPLACEMENT, MAIL_DOT_REPLACEMENT],
                    $email
                );
            }
        });
        /**********
        * COOKIES *
        **********/
        //sets an area cookie
        $this->addTemplateFunction(
            'setAreaCookie',
            function(string $propertyName, $propertyValue){
                return $this->setAreaCookie($propertyName, $propertyValue);
            }
        );
        //sets an area cookie array
        $this->addTemplateFunction(
            'setAreaCookieArray',
            function(array $propertyNames, $propertyValue){
                return $this->setAreaCookieArray($propertyNames, $propertyValue);
            }
        );
        /***********
        * SUBJECTS *
        ***********/
        //builds route to an action from root
        $this->addTemplateFunction(
            'buildRouteToActionFromRoot',
            function(string $actionRoutePart, string $baseroute = null){
                return $this->buildRouteToActionFromRoot($actionRoutePart, $baseroute);
            }
        );
        //Builds route to an action based on action configuration
        $this->addTemplateFunction('buildRouteToAction', function(object $voiceProperties){
            if(isset($voiceProperties->route)) {
                return $voiceProperties->route;
            }  elseif(isset($voiceProperties->routeFromSubject)) {
                return $this->buildRouteToActionFromRoot($voiceProperties->routeFromSubject);
            }  else {
                return '#';
            }
        });
    }
    
    /**
    * Build common template helpers going up the inheritance chain, used to generate templates cache during translations extraction
    */
    protected function buildTemplateHelpersBack()
    {
        $this->buildCommonTemplateHelpers();
    }
    
    /**
    * Sets common template parameters
    */
    private function setCommonTemplateParameters()
    {
        $this->setTemplateParameter('server', $_SERVER);
        $this->setTemplateParameter('environment', ENVIRONMENT);
        $this->setTemplateParameter('brand', BRAND);
        $this->setTemplateParameter('area', $this->area);
        $this->setTemplateParameter('action', $this->action);
        $this->setTemplateParameter('language', $this->language);
        $this->setTemplateParameter('languages', $this->languages);
        $this->setTemplateParameter('labels', $this->labels);
        $this->setTemplateParameter('routeParameters', $this->routeParameters);
        $this->setTemplateParameter('pathToAreaTemplate', sprintf('@local/%s/%s/%s.twig', slugToPSR1Name($this->area, 'class'), TEMPLATES_DEFAULT_FOLDER, $this->area));
        $this->setTemplateParameter('areaCookie', $this->getAreaCookie());
        $this->setTemplateParameter('cookieDuration', COOKIE_DURATION);
    }
    
    /**
    * Adds a template filter
    * @param string $name
    * @param Callable $function
    * @param array $options twig filter options
    */
    protected function addTemplateFilter(string $name, Callable $function, array $options = [])
    {
        $filter = new \Twig\TwigFilter($name, $function, $options);
        $this->template->addFilter($filter);
    }

    /**
    * Adds a template function
    * @param string $name
    * @param Callable $function
    * @param array $options twig filter options
    */
    protected function addTemplateFunction(string $name, Callable $function, array $options = [])
    {
        $filter = new \Twig\TwigFunction($name, $function, $options);
        $this->template->addFunction($filter);
    }

    /**
    * Renders template and injects HTML code into response
    * @param string $templatePath: if null, into current namespace will be searched into 'templates' subfolder a template named after $this->action
    */
    protected function renderTemplateCode(string $templatePath = null): string
    {
        //add templates paths
        $loader = $this->template->getLoader();
        $loader->addPath(SHARE_TEMPLATES_DIR, 'share');
        $loader->addPath(LOCAL_TEMPLATES_DIR, 'local');
        $this->template->setLoader($loader);
        //build default path into calling class namespace
        if(!$templatePath) {
            //turn namespace into an array
            $calledClass = get_called_class();
            $classPath = explode('\\', $calledClass);
            //eliminate namespace first 2 elements (Simplex\Local) and last one (current class name)
            //and add templates default folder
            $templatesFolder = implode('/', array_merge(array_slice($classPath, 2, count($classPath) - 3), [TEMPLATES_DEFAULT_FOLDER]));
            $templatePath = sprintf('@local/%s/%s.%s', $templatesFolder, $this->action, TEMPLATES_EXTENSION);
        }
        //render template and get HTML
        return $this->template->render($templatePath, $this->templateParameters);
    }
    
    /**
    * Renders template and injects HTML code into response
    * @param string $templatePath: if null, into current namespace will be searched into 'templates' subfolder a template named after $this->action
    */
    protected function renderTemplate(string $templatePath = null)
    {
        $html = $this->renderTemplateCode($templatePath);
        //send HTML to response
        $this->output('text/html', $html);
    }
    
    /*************
    * NAVIGATION *
    *************/
    
    /**
    * Checks whether a given navigation route corresponds to the current route
    * @param string $path
    */
    protected function isNavigationRouteCurrentRoute(string $route, ServerRequestInterface $request = null)
    {
        if(!$route) {
            return false;
        }
        //replace route placeholder (which will be substituted by record fields values) with regexp pattern
        $textPattern = '[0-9a-z-_]';
        $patterns = [
            sprintf('~{%s+}~', $textPattern)
        ];
        $replacements = [
            sprintf('%s+', $textPattern)
        ];
        $routePattern = sprintf('~%s~', preg_replace($patterns , $replacements , $route));
        //match the route stripped of placeholders against current one
        $request = $this->request ?? $request;
        $matches = preg_match($routePattern, $request->getUri()->getPath());
        return $matches;
    }
    
    /**
     * Loads area navigation from a file
     * @param string $path: pat to file which return an array of navigations
     * @param ServerRequestInterface $request: needed to check navigation permissions if subject is not handling current route action
     */
    protected function loadNavigation(string $path, ServerRequestInterface $request = null)
    {
        $navigations = require $path;
        foreach ($navigations as $navigationName => &$navigation) {
            $parentVoiceProperties = null;
            $this->loadNavigationLevel($navigationName, $navigation, $parentVoiceProperties, $request);
        }
        $this->navigations = array_merge($this->navigations, $navigations);
        $this->setTemplateParameter('navigations', $this->navigations);
    }
    
    /**
     * Loads area navigation
     */
    protected function loadAreaNavigation(ServerRequestInterface $request = null)
    {
        //check path
        $pathConstant = sprintf('%s_NAVIGATION_PATH', strtoupper($this->area));
        if(!defined($pathConstant) || !is_file(constant($pathConstant))) {
            throw new \Exception(sprintf('constant %s *MUST* be defined for current area and must be a valid path', $pathConstant));
        }
        //load navigation
        $this->loadNavigation(constant($pathConstant), $request);
        //check that there is one navigation named 'area'
        if(!isset($this->navigations['area'])) {
            throw new \Exception('There MUST be a loaded navigation named \'area\'');
        }
    }
    
    /**
     * Gets navigations
     */
    public function getNavigations()
    {
        return $this->navigations;
    }
    
    /**
     * Loads a navigation level
     * @param string $navigationName
     * @param array $navigationLevel
     * @param object $parentVoiceProperties: object with properties of curent level's parent
     * @param ServerRequestInterface $request: needed to check navigation permissions if subject is not handling current route action
     */
    protected function loadNavigationLevel(string $navigationName, array &$loadedNavigationLevel, object &$parentVoiceProperties = null, ServerRequestInterface $request = null)
    {
        foreach ($loadedNavigationLevel as $voiceKey => $voiceProperties) {
            //check explicit visibility flag (possibly set at runtime)
            if(isset($voiceProperties->display) && $voiceProperties->display === false) {
              unset($loadedNavigationLevel[$voiceKey]);
              continue;
            }
            $request = $this->request ?? $request;
            //check voice permission (only if controller has been invoked by router and so request is defined)
            if(
              (
                ($request && $this->needsAuthentication($request))
              )
              && isset($voiceProperties->permissions)
              && !$this->checkAtLeastOnePermission($voiceProperties->permissions, $request)
            ) {
                unset($loadedNavigationLevel[$voiceKey]);
                continue;
            }
            if($parentVoiceProperties) {
                //$voiceProperties->parent = $parentVoiceProperties;
            }
            //check if its current route (only if controller has been invoked by router and so request is defined)
            $route = isset($voiceProperties->route) ? $voiceProperties->route : (isset($voiceProperties->routeFromSubject) ? $this->buildRouteToActionFromRoot($voiceProperties->routeFromSubject) : null);
            if($request && isset($route) && $this->isNavigationRouteCurrentRoute($route, $request)) {
                $voiceProperties->isActive = true;
                $this->currentNavigationVoice[$navigationName] = $voiceKey;
                if($parentVoiceProperties) {
                    $this->setNavigationVoiceParentsActive($parentVoiceProperties);
                }
            }
            //check sub level
            if(isset($voiceProperties->navigation) && !empty($voiceProperties->navigation)) {
                $this->loadNavigationLevel($navigationName, $voiceProperties->navigation, $voiceProperties, $request);
            }
        }
    }
    
    /**
     * Sets recursevely voice parent as active
     * @parm object $parentVoiceProperties
     */
    protected function setNavigationVoiceParentsActive(object $parentVoiceProperties)
    {
        $parentVoiceProperties->isActive = true;
        if(isset($parentVoiceProperties->parent)) {
            $this->setNavigationVoiceParentsActive($parentVoiceProperties->parent);
        }
    }
    
    /**********
    * COOKIES *
    **********/
    
    /**
    * Sets a cookie into current area cookies portion
    * @param string $propertyName: name of property to be set into area cookie
    * @param mixed $propertyValue: value of property to be set into area cookie
    */
    protected function setAreaCookie(string $propertyName, $propertyValue)
    {
        //set area cookie
        $this->cookie->setAreaCookie($this->area, $propertyName, $propertyValue);
        $areaCookie = $this->cookie->getAreaCookie($this->area);
        //update template parameter
        $this->setTemplateParameter('areaCookie', $areaCookie);
    }
    
    /**
    * Sets a cookie into current area cookies portion
    * @param string $propertyName: name of property to be set into area cookie
    * @param mixed $propertyValue: value of property to be set into area cookie
    */
    protected function setAreaCookieArray(array $propertyNames, $propertyValue)
    {
        //get area cookie
        $areaCookie = $this->cookie->getAreaCookie($this->area);
        //get top level property name
        $topPropertyName = array_shift($propertyNames);
        $property =& $areaCookie->$topPropertyName;
        //loop other property names
        foreach ((array) $propertyNames as $propertyName) {
            $property =& $property->$propertyName;
        }
        //set value
        $property = $propertyValue;
        //set area cookie
        $this->cookie->setAreaCookie($this->area, $topPropertyName, $areaCookie->$topPropertyName);
        //update template parameter
        $this->setTemplateParameter('areaCookie', $areaCookie);
    }
    
    /**
    * Gets current area cookie as an object
    * @param string $propertyName: optional property yo be returned
    * @return object the whole area cookie
    */
    protected function getAreaCookie(string $propertyName = null)
    {
        return $this->cookie->getAreaCookie($this->area, $propertyName);
    }
    
    /********
    * FORMS *
    ********/
    
    /**
     * Sets template parameters necessary to file upload
     * to be overridden if necessary by derived classes
     */
    protected function setUploadMaxFilesizeTemplateParameters()
    {
        $uploadMaxFilesizeIni = ini_get('upload_max_filesize');
        $uploadMaxFilesizeBytes = bytes(ini_get('upload_max_filesize'));
        //in kB for client validation
        $uploadMaxFilesizeKB = number_format((float) str_replace('kB', '', \ByteUnits\bytes($uploadMaxFilesizeBytes)->format('kB')), 2, '.', '');
        //in MB to be displayed
        $uploadMaxFilesizeMB = str_replace('MB', '', \ByteUnits\bytes($uploadMaxFilesizeBytes)->format('MB'));
        $this->setTemplateParameter('uploadMaxFilesizeBytes', $uploadMaxFilesizeBytes);
        $this->setTemplateParameter('uploadMaxFilesizeKB', $uploadMaxFilesizeKB);
        $this->setTemplateParameter('uploadMaxFilesizeMB', $uploadMaxFilesizeMB);
    }
    
    /**
    * Processes a recordset to be used in a radio, checkbox or select mapping fields to value and label
    * @param string $valueField
    * @param mixed $labelTokens: the name of one field or an array of fields names and strings to be joined to form label
    * @param array $recordset
    * @param string $languageCode: optional language code to use for localized fields
    * @return array of records
    */
    protected function processRecordsetForInput(
        string $valueField,
        $labelTokens,
        iterable $recordset,
        string $languageCode = null,
        $valueProperty = 'value',
        $labelProperty = 'label',
        $extraFields = []
    ): array
    {
        $items = [];
        $recordType = null;
        foreach ($recordset as $record) {
          //check record type
          if($recordType === null) {
            //file object
            if(get_class($record) == 'SplFileInfo' || get_parent_class($record) == 'SplFileInfo') {
              $recordType = 'file';
            } else {
              $recordType = 'dbRecord';
            }
          }
          //value
          switch ($recordType) {
            case 'dbRecord':
              $value = $record->$valueField;
              $label = $this->buildRecordTokensLabel($labelTokens, $record, $languageCode);
              break;
            case 'file':
              $value = $label = $record->getFilename();
              break;
          }
          //label
          //item
          $item = (object) [
              $valueProperty => $value,
              $labelProperty => $label
          ];
          if(!empty($extraFields)) {
              foreach ($extraFields as $extraField) {
                  $item->$extraField = $record->$extraField;
              }
          }
          $items[] = $item;
        }
        return $items;
    }
    
    /**
    * Builds label for a record using tokens
    * @return string
    */
    protected function buildRecordTokensLabel($labelTokensDefinitions, object $record, string $languageCode = null): string {
      //check labelfields and turn into an array if it's a string
      if(is_string($labelTokensDefinitions)) {
          $labelTokensDefinitions = [$labelTokensDefinitions];
      }
      //check language code
      if(!$languageCode) {
          $languageCode = $this->language->{'ISO-639-1'};
      }
      $label = '';
      $labelTokens = [];
      foreach ($labelTokensDefinitions as $labelToken) {
        //conditional token
        //simple numeric indexed array, first element is an ancestor subject
        if(is_array($labelToken)) {
          if(!isset($this->ancestors[$labelToken[0]])) {
            continue;
          } else {
            $labelTokens[] = $labelToken[1];
          }
        //object
        } elseif(is_object($labelToken)) {
          //check conditions, all of them must be satisfied
          if(isset($labelToken->conditions)) {
            foreach ($labelToken->conditions as $conditionType => $condition) {
              switch($conditionType) {
                case 'fieldNotNull':
                  if(!isset($record->$condition) || !$record->$condition) {
                    continue 3;
                  }
                break;
              }
            }
            //if we've come so far, grab tokens under conditions
            if(isset($labelToken->tokenValues)) {
              if(is_string($labelToken->tokenValues)) {
                $labelToken->tokenValues = [$labelToken->tokenValues];
              }
              if(is_array($labelToken->tokenValues) && !empty($labelToken->tokenValues)) {
                $labelTokens = array_merge($labelTokens, $labelToken->tokenValues);
              }
            }
          }
        //string
        } else {
          $labelTokens[] = $labelToken;
        }
      }
      foreach ($labelTokens as $labelToken) {
        // code...
        //dealing with a record field
        if(property_exists($record, $labelToken)) {
          //localized field
          if(is_array($record->$labelToken)) {
            $tokenLabel = $record->$labelToken[$languageCode];
          } else {
          //not a localized field
            $tokenLabel = $record->$labelToken;
          }
        } else {
        //it's a text token, take it as it is
          $tokenLabel = $labelToken;
        }
        $label .= $tokenLabel;
      }
      return $label;
    }
      
}
