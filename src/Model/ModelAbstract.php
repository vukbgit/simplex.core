<?php
declare(strict_types=1);

namespace Simplex\Model;

use Simplex\PixieExtended;
use Simplex\PixieConnectionExtended;

use Jefs42\LibreTranslate;
use function Simplex\getInstanceNamespace;
use function Simplex\getInstancePath;
use function Simplex\loadLanguages;

/*
 * class that rapresents a model, an atomic structure of data stored in a database
 */
abstract class ModelAbstract extends BaseModelAbstract
{
    /**
     * @var PixieExtended
     */
    protected $query;

    /**
     * @var bool
     * whether model has a position field
     */
    public $hasPositionField;
    
    /**
     * @var bool
     * whether model has a geometry data type fields
     */
    public $hasGeometryFields;
        
    /**
     * @var bool
     * whether to add timestamps to uploaded filenames to avoid names collisions, defaults to true
     */
    public $hasUniqueUploads;
    
    /**
     * @var string
     * String to mark text cloned fields with
     */
    private $cloneMark = '*';
    
    /**
     * Constructor
     * @param PixieExtended $query
     */
    public function __construct(PixieExtended $query)
    {
      parent::__construct('database');
      $this->query = $query;
      
    }

    /*********
    * CONFIG *
    *********/

    /**
     * Parses config
     */
    protected function parseConfig()
    {
      $configPath = $this->buildConfigPath();
      //check table
      if(!isset($this->config->table)) {
        throw new \Exception(sprintf('configuration loaded from file \'%s\' for model %s must contain a \'table\' property', $configPath, getInstanceNamespace($this)));
      }
      //check primary key
      /*if(!isset($this->config->primaryKey)) {
          throw new \Exception(sprintf('configuration loaded from file \'%s\' for model %s must contain a \'primaryKey\' property', $configPath, getInstanceNamespace($this)));
      }else*/if(is_array($this->config->primaryKey)) {
        throw new \Exception(sprintf('Simplex model does not support composite primary keys, model %s configuration defined in %s must expose a single primary key', getInstanceNamespace($this), $configPath));
      }
      //has position field
      $this->hasPositionField = isset($this->config->position) && isset($this->config->position->field) && $this->config->position->field;
      //has geometry fields
      $this->hasGeometryFields = isset($this->config->geometryFields) && is_array($this->config->geometryFields) && !empty($this->config->geometryFields);
      //has unique uploads
      $this->hasUniqueUploads = !isset($this->config->uniqueUploads) || $this->config->uniqueUploads;
    }
    
    /**
     * Returns the query instance
     */
    public function getQuery(): PixieExtended
    {
        return $this->query;
    }
    
    /**
     * Replaces the query instance with another connection
     * @param string $driver
     * @param string $userName
     * @param string $password
     * @param string $database
     * @return void
     */
    public function setQuery(string $driver, string $userName, string $password, string $database): void
    {
      $this->query = new PixieExtended(
      new PixieConnectionExtended(
        $driver,
        [
          'host' => 'localhost',
          'username' => $userName,
          'password' => $password,
          'database' => $database,
          'charset'   => 'utf8',
          'collation' => 'utf8_unicode_ci',
        ]
      )
    );;
    }
    
    /**
     * Returns the table defined
     */
    public function table(): string
    {
        return $this->config->table;
    }
    
    /**
     * Returns the defined view or at least table
     * @param string $view
     */
    public function view(string $view = ''): string
    {
      //return $this->config->view ? (($this->hasLocales() || (isset($this->config->useLocalizedView) && $this->config->useLocalizedView)) ? sprintf('%s_locales', $this->config->view) : $this->config->view) : $this->config->table;
      //custom view
      if($view) {
        return $view;
      //default views
      } elseif(isset($this->config->view)) {
        //localized view
        if($this->hasLocales() || (isset($this->config->useLocalizedView) && $this->config->useLocalizedView)) {
          return sprintf('%s_locales', $this->config->view);
        } else {
          return $this->config->view;
        }
      //table
      } else {
        return $this->config->table;
      }
    }
    
    /**
    * Returns whether the model has at least one localized field
    */
    public function hasLocales(): bool
    {
        return isset($this->config->locales) && !empty($this->config->locales);
    }
    
    /**
    * Returns whether the model has at least one upload
    */
    public function hasUploads(): bool
    {
        return isset($this->config->uploads) && !empty($this->config->uploads);
    }

    /**
    * Returns the configured upload keys names
    */
    public function getUploadKeys(): array
    {
        if($this->hasUploads()) {
            return array_keys($this->config->uploads);
        } else {
            return [];
        }
    }

    /**
    * Returns the configured outputs names for an upload key
    * @param string $uploadKey
    */
    public function getUploadKeyOutputs(string $uploadKey): array
    {
        if($this->hasUploads()) {
            return array_keys($this->config->uploads[$uploadKey]);
        } else {
            return [];
        }
    }

    /*******************
    * DEBUG & MESSAGES *
    *******************/

    /**
    * Ouputs last sql
    */
    public function sql(): string
    {
        return $this->query->sql();
    }

    /**
    * Handles an exception using error codes (see https://docstore.mik.ua/orelly/java-ent/jenut/ch08_06.htm)
    * @param Exception $exception
    * @return object to be used for alert display with the following properties:
    *   ->code: alphanumeric message code
    *   ->data: an array with any specific error code relevant data (such as involved field names)
    */
    public function handleException(\Exception $exception): object
    {
        //get error code and message
        $errorCode = (string) $exception->getCode();
        $errorMessage = $exception->getMessage();
        //extract SQL-92 error class and subclass from code
        //proper PDO exception
        if(strlen((string) $errorCode) >= 2) {
            $class = substr($errorCode, 0, 2);
            $subclass = substr($errorCode, 2);
        } else {
        //probably a custom PDO exception with a fake code thrown to be displaied into UI
            $class = null;
            $subclass = null;
        }
        $rawMessage = null;
        $data = null;
        switch($class) {
            //Data exception
            case '22':
              //standard subclass stop at 027, so we define custom error from 50 onward
              if((int) $subclass >= 50) {
                $code = sprintf('SQLSTATE_%s', $errorCode);
                //data can only be passed through message
                $data = explode('|', $errorMessage);
              } else {
                $code = null;
                $rawMessage = sprintf('error code: %s; error message: %s', $errorCode, $errorMessage);
              }
            break;
            //Integrity constraint violation
            case '23':
                //duplicate entry
                $errorType = false;
                if(preg_match('/Duplicate entry/', $errorMessage) === 1) {
                    $errorType = 'duplicate_entry';
                    //extract field name
                    preg_match("/'([0-9a-zA-Z_]+)'$/", $errorMessage, $matches);
                    $data = [$matches[1]];
                }
                if(preg_match('/duplicate key value/', $errorMessage) === 1) {
                    $errorCode = '23000';
                    $errorType = 'duplicate_entry';
                    //extract field name
                    preg_match("/Key \(([0-9a-zA-Z_ ,]+)\)/", $errorMessage, $matches);
                    $data = [$matches[1]];
                }
                //failed foreign key constraint
                if(preg_match('/a foreign key constraint fails/', $errorMessage) === 1) {
                    $errorType = 'fk_constraint';
                    //extract field name
                    preg_match("/FOREIGN KEY \(`([0-9a-zA-Z_]+)`\)/", $errorMessage, $matches);
                    $data = [$matches[1]];
                }
                //null value on mandatory column
                if(preg_match('/Column \'[0-9a-zA-Z_]+\' cannot be null/', $errorMessage) === 1) {
                    $errorType = 'mandatory_null';
                    //extract field name
                    preg_match("/Column \'([0-9a-zA-Z_]+)\'/", $errorMessage, $matches);
                    $data = [$matches[1]];
                }
                if(!$errorType) {
                    $code = null;
                    $rawMessage = sprintf('error code: %s; error message: %s', $errorCode, $errorMessage);
                } else {
                    $code = sprintf('SQLSTATE_%s_%s', $errorCode, $errorType);
                }
            break;
            //Column not found
            case '42':
                if(preg_match('/Column not found/', $errorMessage) === 1) {
                    $errorType = 'column_not_found';
                    //extract field name
                    preg_match("/Unknown column '([0-9a-zA-Z_]+)'/", $errorMessage, $matches);
                    $data = [$matches[1]];
                } else {
                    xx($exception);
                }
                $code = sprintf('SQLSTATE_%s_%s', $errorCode, $errorType);
            break;
            //Object not in prerequisite state, PostgreSQL primary key without sequence
            case '55':
                if(preg_match('/Object not in prerequisite state/', $errorMessage) === 1) {
                    $errorType = 'object_not_in_prerequisite_state';
                } else {
                    xx($exception);
                }
                $code = sprintf('SQLSTATE_%s_%s', $errorCode, $errorType);
            break;
            default:
                $code = null;
                $rawMessage = sprintf('error code: %s; error message: %s', $errorCode, $errorMessage);
            break;
        }
        return (object) [
            'erroCode' => $errorCode,
            'code' => $code,
            'data' => $data,
            'rawMessage' => $rawMessage
        ];
    }
    
    /********************
    * FIELDS PROCESSING *
    ********************/

    /**
    * turns a date from the locale format to YYYY-MM-DD
    * @param string $fromFormat: locale format
    * @param string $date
    */
    public function formatDate(string $fromFormat, $date)
    {
        if($date) {
            $date = \DateTime::createFromFormat($fromFormat, $date);
            return $date->format('Y-m-d');
        } else {
            return null;
        }
    }
    
    /**
    * turns a datetime from the locale format to YYYY-MM-DD
    * @param string $fromFormat: locale format
    * @param string $datetime
    */
    public function formatDateTime(string $fromFormat, $datetime)
    {
        if($datetime) {
            $date = \DateTime::createFromFormat($fromFormat, $datetime);
            return $date->format('Y-m-d H:i');
        } else {
            return null;
        }
    }
    
    /**
    * Builds a raw field
    * @param string $sql: the piece of SQL code to be used for field
    */
    public function rawField(string $sql)
    {
        return $this->query->raw($sql);
    }
    
    /***************
    * MAINTAINANCE *
    ***************/
    
    /**
    * Resets auto increment
    * @param string $table
    */
    public function resetAutoIncrement()
    {
        $this->query
            ->statement(sprintf('ALTER TABLE %s AUTO_INCREMENT = 1;', $this->table()));
        if($this->hasLocales()) {
            $this->query
                ->statement(sprintf('ALTER TABLE %s AUTO_INCREMENT = 1;', $this->localesTable()));
        }
        if($this->hasUploads()) {
            $this->query
                ->statement(sprintf('ALTER TABLE %s AUTO_INCREMENT = 1;', $this->uploadTable()));
        }
    }
    
    /*********
    * SELECT *
    *********/

    /**
    * Gets a recordset
    * @param array $where: see Simplex\PixieExtended::buildWhere for details
    * @param array $order: array of arrays, each with 1 element (field name, direction defaults to 'ASC') or 2 elements (field name, order 'ASC' | 'DESC')
    * @param int $limit
    * @param array $extraFields: any other field to get in addition to the ones defined into table/view for example:
    *                fields aliases
    *                fields based on runtime variables
    * @param string $view
    */
    public function get(array $where = [], array $order = [], int $limit = null, array $extraFields = [], string $view = ''): array
    {
        //table
        $this->query
            ->table($this->view($view))
            ->select('*');
        if(!empty($extraFields)) {
          $this->query
            ->select($extraFields);
        }
        //where conditions
        $this->query->buildWhere($where);
        //order
        if(!empty($order)) {
            foreach ($order as $orderCondition) {
                call_user_func_array([$this->query, 'orderBy'], $orderCondition);
            }
        }
        if($limit) {
            $this->query->limit($limit);
        }
        $records = $this->query->get();
        //decode fields
        foreach($records as &$record) {
          foreach($record as &$fieldValue) {
            if(is_string($fieldValue)) {
              $fieldValue = html_entity_decode($fieldValue);
            }
          }
        }
        //localized fields
        $records = $this->extractLocales($records);
        //xx($records);
        return $records;
    }
    
    /**
    * Process locales into a recordset
    * @param array $records
    */
    public function extractLocales(array $records)
    {
        if($this->hasLocales()) {
            $recordsByPK = [];
            $languagesCodes = array_keys(get_object_vars(loadLanguages('local')));
            $localizedFieldValuesTemplate = [];
            foreach ($languagesCodes as $languageCode) {
                $localizedFieldValuesTemplate[$languageCode] = null;
            }
            foreach ($records as $record) {
                $PKValue = $record->{$this->config->primaryKey};
                //init record by PK
                if(!isset($recordsByPK[$PKValue])) {
                    $recordsByPK[$PKValue] = (object) [
                    ];
                    foreach ($record as $field => $value) {
                        //skip language code field
                        if($field == 'language_code') {
                            continue;
                        }
                        //if it's not a localized field store as is
                        if(!in_array($field, $this->config->locales)) {
                            $recordsByPK[$PKValue]->$field = $value;
                        } else {
                        //if it's a localized field init field's values container
                            $recordsByPK[$PKValue]->$field = $localizedFieldValuesTemplate;
                        }
                    }
                }
                //loop record's localized fields
                foreach ($this->config->locales as $field) {
                    //in case a locale field is added lately
                    if(isset($record->$field)) {
                        $recordsByPK[$PKValue]->$field[$record->language_code] = $record->$field;
                    } else {
                        $recordsByPK[$PKValue]->$field[$record->language_code] = null;
                    }
                }
            }
            $records = array_values($recordsByPK);
        }
        return $records;
    }
    
    /**
    * Gets a record
    * @param array $where: where conditions, see get() method for details
    */
    public function first(array $where = [])
    {
      $view = $this->config->view_first ?? '';
      return current($this->get($where, [], null, [], $view));
    }
    
    /********
    * INSERT *
    ********/
    
    /**
    * Inserts a record
    * @param array $fieldsValues: indexes are fields names, values are fields values, it can be an array of arrays in case of batch insert
    * @return mixed primary key of inserted records or array in case of batch insert
    */
    public function insert(array &$fieldsValues)
    {
      //values are indexed array -> batch insert
      $batchInsert = array_is_list($fieldsValues);
      //geometry fields
      $this->buildGeometryFieldsSaveSql($fieldsValues);
      //insert record
      $primaryKeyValue = $this->query
          ->table($this->table())
          ->insert($fieldsValues);
      //add primary key to values
      if(!$batchInsert) {
        $fieldsValues[$this->config->primaryKey] = $primaryKeyValue;
      }
      return $primaryKeyValue;
    }
    
    /*********
    * UPDATE *
    *********/
    
    /**
    * Updates a record
    * @param mixed $primaryKeyValue
    * @param array $fieldsValues: indexes are fields names, values are fields values
    */
    public function update($primaryKeyValue = null, array &$fieldsValues = [])
    {
      //geometry fields
      $this->buildGeometryFieldsSaveSql($fieldsValues);
      $this->query
          ->table($this->table());
      if($primaryKeyValue) {
          $this->query
              ->where($this->config->primaryKey, $primaryKeyValue);
      }
      $this->query
          ->update($fieldsValues);
      //add primary key to values
      $fieldsValues[$this->config->primaryKey] = $primaryKeyValue;
    }
    
    /*********
    * DELETE *
    *********/
    
    /**
    * Deletes a record by primary key value and/or other where conditions
    * @param mixed $primaryKeyValue
    * @param array $where: see Simplex\PixieExtended::buildWhere for details
    * @param boolean $emptyTable: whether to really run query without a primary key value or any other condition
    */
    public function delete($primaryKeyValue = null, array $where = [], bool $emptyTable = false)
    {
      //check that a where condition is set
      if($primaryKeyValue == null && empty($where) && $emptyTable === false) {
        throw new \PDOException(sprintf('Trying to delete from table %s without any where condition!', $this->table()), 1);
      }
        //set where conditions
        if($primaryKeyValue) {
            $where = array_merge(
                $where,
                [
                    [$this->config->primaryKey, $primaryKeyValue]
                ]
            );
        }
        //uploads
        if($this->hasUploads()) {
            //get uploaded files to check for deletion
            $uploadedFilesToDelete = $this->getUploadedFiles($where);
        }
        //delete record
        $this->query
            ->table($this->table());
        $this->query->buildWhere($where);
        $this->query->delete();
        //uploads
        if($this->hasUploads()) {
            $uploadKeys = $this->getUploadKeys();
            //group files by upload key
            $uploadedFilesByUploadKey = [];
            foreach ($uploadKeys as $uploadKey) {
                $uploadedFilesByUploadKey[$uploadKey] = [];
            }
            foreach ($uploadedFilesToDelete as $uploadedFileToDelete) {
                $uploadedFilesByUploadKey[$uploadedFileToDelete->upload_key][] = $uploadedFileToDelete;
            }
            foreach ($uploadedFilesByUploadKey as $uploadKey => $uploadedFilesToDelete) {
                $this->unlinkUploadedFiles($uploadKey, $uploadedFilesToDelete);
            }
        }
    }
    
    /********
    * CLONE *
    ********/
    
    /**
    * Cloines one or more records record
    * @param mixed $primaryKeyValues: a single primary key or an array of values for batch cloning
    * @param array $fieldsToMark: text fields to be marked
    * @param array $fieldsToUpdate: array indexed by fields names for fields whose values is to be changed (i.e. foreign keys)
    * @return array with primaryt keys of cloned records
    */
    public function clone($primaryKeyValues, array $fieldsToMark, array $fieldsToUpdate = [])
    {
        if(!is_array($primaryKeyValues)) {
            $primaryKeyValues = [$primaryKeyValues];
        }
        $clonedRrecordsPrimaryKeyValues = [];
        foreach ($primaryKeyValues as $originalPrimaryKeyValue) {
            $fieldsValues = (array) $this->query
                ->table($this->table())
                ->where($this->config->primaryKey, $originalPrimaryKeyValue)
                ->first();
            unset($fieldsValues[$this->config->primaryKey]);
            //fields to mark
            foreach ($fieldsToMark as $fieldToMark) {
                if(isset($fieldsValues[$fieldToMark])) {
                    $fieldsValues[$fieldToMark] = sprintf('%s%s', $this->cloneMark, $fieldsValues[$fieldToMark]);
                }
            }
            //fields to update
            foreach ($fieldsToUpdate as $field => $value) {
                $fieldsValues[$field] = $value;
            }
            //insert record
            $clonePrimaryKeyValue = $this->query
                ->table($this->table())
                ->insert($fieldsValues);
            $clonedRrecordsPrimaryKeyValues[] = $clonePrimaryKeyValue;
            //locales
            if($this->hasLocales()) {
                $localesTableName = $this->localesTable();
                $localesPrimaryKeyField = sprintf('%s_id', $localesTableName);
                $localesRecords = $this->query
                    ->table($localesTableName)
                    ->where($this->config->primaryKey, $originalPrimaryKeyValue)
                    ->get();
                foreach ((array) $localesRecords as $localesRecord) {
                    $localesRecord = (array) $localesRecord;
                    unset($localesRecord[$localesPrimaryKeyField]);
                    $localesRecord[$this->config->primaryKey] = $clonePrimaryKeyValue;
                    foreach ($fieldsToMark as $fieldToMark) {
                        if(isset($localesRecord[$fieldToMark])) {
                            $localesRecord[$fieldToMark] = sprintf('%s%s', $this->cloneMark, $localesRecord[$fieldToMark]);
                        }
                    }
                    $this->query
                        ->table($localesTableName)
                        ->insert($localesRecord);
                }
            }
            //uploads
            if($this->hasUploads()) {
                $uploadsTableName = $this->uploadTable();
                $uploadsPrimaryKeyField = sprintf('%s_id', $uploadsTableName);
                $uploadRecords = $this->getUploadedFiles([[$this->config->primaryKey, $originalPrimaryKeyValue]]);
                foreach ((array) $uploadRecords as $uploadRecord) {
                    $uploadRecord = (array) $uploadRecord;
                    unset($uploadRecord[$uploadsPrimaryKeyField]);
                    $uploadRecord[$this->config->primaryKey] = $clonePrimaryKeyValue;
                    $this->query
                        ->table($uploadsTableName)
                        ->insert($uploadRecord);
                }
            }
        }
        return $clonedRrecordsPrimaryKeyValues;
    }
    
    /**********
    * LOCALES *
    **********/
    
    /**
    * Builds locales table name
    */
    public function localesTable()
    {
        return sprintf('%s_locales', $this->table());
    }
    
    /**
    * Saves locales values
    * @param mixed $primaryKeyValue
    * @param array $localesValues: indexes are language codes, values are array indexed by localized fields name with localized values
    */
    public function saveLocales($primaryKeyValue, $localesValues)
    {
      //automatic translations
      if(defined('AUTOMATIC_TRANSLATIONS')) {
        global $DIContainer;
        $translator = $DIContainer->get('translator');
      }
      //check locales table
      $localesTableName = $this->localesTable();
      if (!$this->query->tableExists($localesTableName)) {
        throw new \Exception(sprintf('missing %s locales tables for model %s', $localesTableName, getInstanceNamespace($this)));
      }
      //reset values
      $this->query
        ->table($localesTableName)
        ->where($this->config->primaryKey, $primaryKeyValue)
        ->delete();
      //loop fields
      $records = [];
      foreach ($localesValues as $languageCode => $fieldLocalesValues) {
        $record = [
          'language_code' => $languageCode,
          $this->config->primaryKey => $primaryKeyValue
        ];
        //loop languages
        foreach ($fieldLocalesValues as $fieldName => $fieldValue) {
          //automatic translations
          if(
            defined('AUTOMATIC_TRANSLATIONS')
            &&
            !$fieldValue
            &&
            $languageCode !== constant('AUTOMATIC_TRANSLATIONS')->defaultSourceLanguage
            &&
            $localesValues[constant('AUTOMATIC_TRANSLATIONS')->defaultSourceLanguage][$fieldName]
          ) {
            $record[$fieldName] = $translator->translate(
              text: $localesValues[constant('AUTOMATIC_TRANSLATIONS')->defaultSourceLanguage][$fieldName],
              target: $languageCode
            );
          } else {
            $record[$fieldName] = $fieldValue;
          }
        }
        $records[] = $record;
      }
      $this->query
        ->table($localesTableName)
        ->insert($records);
    }
    
    /**********
    * UPLOADS *
    **********/
    
    /**
    * Gets the uploads folder
    */
    public function getUploadsFolder(): string
    {
        return str_replace('private/', 'public/', getInstancePath($this));
    }
    
    /**
    * Gets an upload folder
    * @param string $uploadKey
    */
    public function getUploadFolder(string $uploadKey): string
    {
        return sprintf('%s/%s', $this->getUploadsFolder(), $uploadKey);
    }
    
    /**
    * Gets an output folder
    * @param string $uploadKey
    * @param string $outputKey
    */
    public function getOutputFolder(string $uploadKey, string $outputKey): string
    {
        return sprintf('%s/%s', $this->getUploadFolder($uploadKey), $outputKey);
    }
    
    /**
    * Gets an uploaded file absolute path for an output
    * @param string $uploadKey
    * @param string $outputKey
    * @param string $fileName
    */
    public function getOutputFilePath(string $uploadKey, string $outputKey, string $fileName): string
    {
        return sprintf('%s/%s/%s', $this->getUploadFolder($uploadKey), $outputKey, $fileName);
        //return str_replace(ABS_PATH_TO_ROOT, '', $absolutePath);
        return str_replace('public/local/simplex', '/' . PUBLIC_LOCAL_SIMPLEX_DIR, $path);
        
    }
    
    /**
    * Gets an uploaded file path to be used into templates
    * @param string $uploadKey
    * @param string $outputKey
    * @param string $fileName
    */
    public function getPublicOutputFilePath(string $uploadKey, string $outputKey, string $fileName): string
    {
        $absolutePath = $this->getOutputFilePath($uploadKey, $outputKey, $fileName);
        return str_replace('public/local', '/' . PUBLIC_LOCAL_DIR, str_replace(ABS_PATH_TO_ROOT . '/', '', $absolutePath));
        
    }
    
    /**
    * Gets the uploads table name
    */
    protected function uploadTable(): string
    {
        return sprintf('%s_uploads', $this->table());
    }
    
    /**
    * Gets uploads records
    * @param array $where: array of arrays, each with 2 elements (field name and value, operator defaults to '=') or 3 elements (field name, operator, value)
    */
    protected function getUploadedFiles($where): array
    {
        $this->query
            ->table($this->uploadTable());
        //where conditions
        if(!empty($where)) {
            $this->query->buildWhere($where);
        }
        return $this->query->get();
    }
    
    /**
    * Gets record uploaded files names
    * @param mixed $primaryKeyValue: value of record primary key field
    * @param string $uploadKey
    */
    protected function getUploadedFilesNames($primaryKeyValue, string $uploadKey = null): array
    {
        $this->query
            ->table($this->uploadTable())
            ->where($this->config->primaryKey, $primaryKeyValue);
        if($uploadKey) {
            $this->query->where('upload_key', $uploadKey);
        }
        $uploadedFiles = $this->query->get();
        //extract uploaded files names
        $uploadedFilesNames = array_map(
            function($record) {
                return $record->file_name;
            },
            $uploadedFiles
        );
        return $uploadedFilesNames;
    }
    
    /**
    * Delete uploads records
    * @param mixed $primaryKeyValue: value of record primary key field
    * @param mixed $uploadKey
    */
    protected function deleteUploadedFiles($primaryKeyValue, string $uploadKey = null)
    {
        $this->query
            ->table($this->uploadTable())
            ->where($this->config->primaryKey, $primaryKeyValue);
        if($uploadKey) {
            $this->query->where('upload_key', $uploadKey);
        }
        $this->query->delete();
    }
    
    /**
    * Creates the uploads table
    */
    public function createUploadsTable()
    {
        $uploadTableName = $this->uploadTable();
        $uploadTablePrimaryKeyField = sprintf('%s_id', $uploadTableName);
        $modelTableFK = sprintf('%s_ibfk_1', $uploadTableName);
        $sql = <<<EOT
        CREATE TABLE `$uploadTableName` (
          `$uploadTablePrimaryKeyField` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `{$this->config->primaryKey}` int(10) unsigned NOT NULL,
          `upload_key` varchar(64) NOT NULL,
          `file_name` varchar(256) NOT NULL,
          PRIMARY KEY (`$uploadTablePrimaryKeyField`),
          KEY `{$this->config->primaryKey}` (`{$this->config->primaryKey}`),
          CONSTRAINT `$modelTableFK` FOREIGN KEY (`{$this->config->primaryKey}`) REFERENCES `{$this->table()}` (`{$this->config->primaryKey}`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOT;
        $this->query->query($sql);
    }
    
    /**
    * Saves uploads values
    * @param mixed $primaryKeyValue
    * @param object $uploadsValues: indexes are uploads keys, values are strings with images names separated by |
    */
    public function saveUploadsFiles($primaryKeyValue, object $uploadsValues)
    {
        //create uploads table if necessary
        //DISMISSED: when writing subject view uplaods table must already be defined
        /*$uploadTableName = sprintf('%s_uploads', $this->table());
        if (!$this->query->tableExists($uploadTableName)) {
            $this->createUploadsTable();
        }*/
        //loop uploads
        foreach ($this->getUploadKeys() as $uploadKey) {
          if(isset($uploadsValues->$uploadKey)) {
            $filesList = $uploadsValues->$uploadKey ? explode('|', $uploadsValues->$uploadKey) : null;
            $this->saveUploadFiles($primaryKeyValue, $uploadKey, $filesList);
          }
        }
    }
    
    /**
    * Saves uploads values
    * @param mixed $primaryKeyValue
    * @param string $uploadKey
    * @param array $filesList: array of file names
    */
    public function saveUploadFiles($primaryKeyValue, string $uploadKey, array $filesList = null)
    {
        //get upload files and candidate them for deletion
        $uploadedFilesToDelete = $this->getUploadedFiles([
            [$this->config->primaryKey, $primaryKeyValue],
            ['upload_key', $uploadKey]
        ]);
        //reset upload files
        $this->deleteUploadedFiles($primaryKeyValue, $uploadKey);
        foreach((array) $filesList as $fileName) {
            //look for file into record uploaded files
            if (($uploadedIndex = array_search($fileName, $uploadedFilesToDelete)) !== false) {
                //remove this file from the ones to be deleted
                unset($uploadedFilesToDelete[$uploadedIndex]);
            }
            //save record
            $record = [
                $this->config->primaryKey => $primaryKeyValue,
                'upload_key' => $uploadKey,
                'file_name' => $fileName
            ];
            $this->query
                ->table($this->uploadTable())
                ->insert($record);
        }
        //handle files no longer needed by this upload key deletion
        $this->unlinkUploadedFiles($uploadKey, $uploadedFilesToDelete);
    }
    
    /**
    * Saves uploads values
    * @param string $uploadKey
    * @param array $uploadedFilesToDelete
    */
    protected function unlinkUploadedFiles(string $uploadKey, array $uploadedFilesToDelete)
    {
        //loop files
        foreach ($uploadedFilesToDelete as $uploadedFileToDelete) {
            //check if the file is used by some other record
            $isFileInUse = $this->getUploadedFiles([
                ['upload_key', $uploadKey],
                ['file_name', $uploadedFileToDelete->file_name]
            ]);
            if(!$isFileInUse) {
                //loop outputs
                foreach ($this->getUploadKeyOutputs($uploadKey) as $outputKey) {
                    $outputFilePath = $this->getOutputFilePath($uploadKey, $outputKey, $uploadedFileToDelete->file_name);
                    if(is_file($outputFilePath)) {
                        unlink($outputFilePath);
                    }
                }
            }
        }
    }
    
    /***********
    * POSITION *
    ***********/
    
    /**
    * Gets next free position
    * @param array $contextFieldsValues indexed by field names to narrow context in which to llok for next position
    */
    public function getNextPosition(array $contextFieldsValues = [])
    {
        $this->query
            ->table($this->view());
            foreach ($contextFieldsValues as $field => $value) {
                $this->query->where($field, $value);
            }
        $lastRecord = $this->query->orderBy($this->config->position->field, 'DESC')
            ->first();
        $positionField = $this->config->position->field;
        return $lastRecord ? $lastRecord->$positionField + 1 : 1;
    }
    
    /**
     * Moves record up/down
     * @param mixed $primaryKeyValue
     * @param string $direction: up | down
     */
    public function changeRecordPosition($primaryKeyValue, $direction)
    {
        $record = $this->first(
            [
                [$this->config->primaryKey, $primaryKeyValue]
            ]
        );
        $positionField = $this->config->position->field;
        //direction
        switch ($direction) {
            case 'down':
                $siblingPosition = $record->$positionField + 1;
            break;
            case 'up':
                $siblingPosition = $record->$positionField - 1;
            break;
        }
        //get sibling
        $this->query
            ->table($this->table());
        //filter
        foreach ((array) $this->config->position->contextFields as $contextField) {
            $this->query->where($contextField, $record->$contextField);
        }
        //position
        $this->query->where($positionField, $siblingPosition);
        $sibling = $this->query->first();
        //switch positions
        $this->query
            ->table($this->table())
            ->where($this->config->primaryKey, $record->{$this->config->primaryKey})
            ->update([$positionField => $sibling->$positionField]);
        $this->query
            ->table($this->table())
            ->where($this->config->primaryKey, $sibling->{$this->config->primaryKey})
            ->update([$positionField => $record->$positionField]);
    }
    
    /***********
    * CALENDAR *
    ***********/
    
    /**
    * Maps records to calendar events using a map defined into $this->config->calendarFieldsMap in the form of an object structured this way:
    * field-to-property: fullcalendar-event-objecty-property->db-field
    * fields-to-property: fullcalendar-event-objecty-property->[db-field-1|string...]
    * @return array of objects with properties as described into https://fullcalendar.io/docs/event-object
    */
    public function mapRecordsToCalendarEvents($records): array
    {
      //check fields map
      if(!isset($this->getConfig()->calendarFieldsMap)) {
        throw new \Exception(sprintf('current class "%s" must implement a model config "calendarFieldsMap" property', static::class));
      }
      $events = [];
      //loop records
      foreach ((array) $records as $record) {
        $event = new \stdClass;
        //loop fields map
        foreach ($this->getConfig()->calendarFieldsMap as $calendarProperty => $tokens) {
          //single field
          if(!is_array($tokens)) {
            $event->$calendarProperty = $record->$tokens;
          } else {
          //multiple fields
            $value = '';
            foreach ($tokens as $token) {
              //field
              if(property_exists($record, $token)) {
                $value .= $record->$token;
              } else {
              //string
                $value .= $token;
              }
              $event->$calendarProperty = $value;
            }
          }
        }
        $events[] = $event;
      }
      return $events;
    }
    
    /******************
    * GEOMETRY FIELDS *
    ******************/
    
    /**
    * builds SQL to save geometry fields
    */
    public function buildGeometryFieldsSaveSql(&$fieldsValues)
    {
      if($this->hasGeometryFields) {
        //values are associative array -> not batch insert
        if(!array_is_list($fieldsValues)) {
          $batchInsert = false;
          $tmpFieldsValues = [$fieldsValues];
        } else {
          $batchInsert = true;
          $tmpFieldsValues = $fieldsValues;
        }
        foreach($tmpFieldsValues as &$tmpFieldValue) {
          foreach($this->config->geometryFields as $fieldName => $dataType) {
            if(isset($tmpFieldValue[$fieldName])) {
              $value = $tmpFieldValue[$fieldName];
              switch ($dataType) {
                case 'point':
                  list($latitude, $longitude) = explode(',', $value);
                  $tmpFieldValue[$fieldName] = $this->rawField(sprintf('POINT(%s, %s)', $latitude, $longitude));
                break;
              }
            }
          }
        }
        if(!$batchInsert) {
          $fieldsValues = $tmpFieldsValues[0];
        } else {
          $fieldsValues = $tmpFieldsValues;
        }
      }
    }
}
