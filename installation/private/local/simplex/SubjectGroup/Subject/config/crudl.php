<?php
return (object) [
    'fields' => [
        'PRIMARY-KEY-FIELD' => (object) [
            'table' => (object)[
                //boolean defaults to true
                'filter' => false,
                //boolean defaults to false
                'total' => false
            ],
            //input filter see https://www.php.net/manual/en/filter.filters.php
            'inputFilter' => FILTER_VALIDATE_INT,
        ],
        'VARCHAR-FIELD' => (object) [
            'table' => (object)[
                //boolean defaults to true
                'filter' => true,
                //boolean defaults to false
                'total' => false
            ],
            //input filter see https://www.php.net/manual/en/filter.filters.php
            'inputFilter' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_NO_ENCODE_QUOTES],
        ]
    ]
];
