<?php
//import subject variables
require 'variables.php';
//import model configuration
$modelConfig = require 'model.php';
return [
    'globalActions' => [
        'list' => (object) [
            'routeFromSubject' => 'list',
            'permissions' => [sprintf('manage-%s', $subject)],
        ],
        'insert-form' => (object) [
            'routeFromSubject' => 'insert-form',
            'permissions' => [sprintf('manage-%s', $subject)],
        ]
    ],
    //for record actions placeholder enclosed by curly brackets {} will be substituded by field values found into record
    'recordVisibleActions' => [
        //manage children action
        /*'CHILD-SUBJECT' => (object) [
            'routeFromSubject' => sprintf('{%s}/CHILD-SUBJECT/list', $modelConfig->primaryKey),
            'permissions' => ['manage-CHILD-SUBJECT'],
        ],*/
        //manage record position
        /*'move-down' => (object) [
            'routeFromSubject' => sprintf('move-record-down/{%s}', $modelConfig->primaryKey),
            'permissions' => [sprintf('manage-%s', $subject)],
            'linkClass' => 'icon-erp-triangle-down',
            'conditions' => ['moveDown']
        ],
        'move-up' => (object) [
            'routeFromSubject' => sprintf('move-record-up/{%s}', $modelConfig->primaryKey),
            'permissions' => [sprintf('manage-%s', $subject)],
            'linkClass' => 'icon-erp-triangle-up',
            'conditions' => ['moveUp']
        ],*/
        'update-form' => (object) [
            'routeFromSubject' => sprintf('update-form/{%s}', $modelConfig->primaryKey),
            'permissions' => [sprintf('manage-%s', $subject)],
        ],
    ],
    'recordHiddenActions' => [
      'delete-form' => (object) [
        'routeFromSubject' => sprintf('delete-form/{%s}', $modelConfig->primaryKey),
        'permissions' => [sprintf('manage-%s', $subject)],
      ]
    ],
    'recordMenuActions' => [
        'update-form'
    ],
    'bulkActions' => [
      'delete-bulk' => (object) [
        'routeFromSubject' => 'delete-bulk',
        'permissions' => [sprintf('manage-%s', $subject)],
        'noConfirm' => false,
        //custom confirm message, the key of a label with path subject / 'alerts' / key, format() filter will be applied with action label as argument
        //'confirm_message_label' => 'label-key',
        //to change form target to _blank
        'blank' => false,
      ]
    ],
];
