<?php
//import subject variables
require 'variables.php';
return (object) [
  //DB SOURCE
  'table' => 'TABLE-NAME',
  'view' => 'VIEW-NAME',
  'primaryKey' => 'PRIMARY-KEY-FIELD',
  //primary key alias is useful in contexts where table primary key proper name cannot be used
  //i.e: all of schema table have primary key field named 'id', when used as foreign key in db views or into routes definitions these fields *will* be aliased
  //'primaryKeyAlias' => 'PRIMARY-KEY-FIELD-ALIAS',
  //whether to add timestamps to uploaded filenames to avoid names collisions, defaults to true
  //'uniqueUploads' => true,
  'uploads' => [
    'UPLOAD-KEY' => [
      'OUTPUT-WITHOUT-HANDLER-KEY' => (object) [],
      'OUTPUT-WITH-HANDLER-KEY' => (object) [
        //instance method, use special first element 'this' 
        'handler' => ['this','NON-STATIC-METHOD-NAME'],
        //static method
        'handler' => [sprintf('\%s\Controller', $subjectNamespace),'STATIC-METHOD-NAME'],
        'parameters' => [PARAMETER1,...]
      ]
    ]
  ],
  //in case there is a TABLE-NAME_locales table
  'locales' => [
    'LOCALE-FIELD-1',
    'LOCALE-FIELD-2',
  ],
  //force using a locales view, to be used in case in case there is NOT a TABLE-NAME_locales table but some foreign keys tables are localized and therefore there is a localized view
  //'useLocalizedView' => true,
  //whether model has a poistion field
  'position' => (object) [
    'field' => false,
    //fields to narrow by next position look up
    'contextFields' => []
  ],
  //map from table fields to fullcalendar event object properties (https://fullcalendar.io/docs/event-object)
  'calendarFieldsMap' => (object) [
    'FULLCALENDAR-EVENT-PROPERTY' => 'DB-FIELD',
    'FULLCALENDAR-EVENT-PROPERTY' => ['DB-FIELD-1', 'ANY-STRING', 'DB-FIELD-2'],
  ],
  /*//FILE SYSTEM SOURCE
  //root folder
  'rootFolder' => 'public/PATH-TO-FOLDER',
  //whether to include folders into results, default true
  'findFolders' => false,
  //whether to include file into results, default true
  'findFiles' => true,
  //fixed masks to filter results by
  //indexed by criteria
  'masks' => [
    //glob masks
    'name' => ['*.xxx'],
    //file extensions, more than one makes no sense at the moment
    //'type' => ['pdf'],
    //modification time, with one element operator is assumed = otherwise can be =, >, >=, <, <=
    //'mTime' => [['YYYY-MM-DD', '=']],
    //file size in bytes, with one element operator is assumed = otherwise can be =, >, >=, <, <=
    //'size' => [[XXXXXX, '<']],
  ],
  */
];
