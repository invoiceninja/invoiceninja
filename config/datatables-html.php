<?php

return [
    /*
     * Default table attributes when generating the table.
     */
    'table' => [
        'class' => 'table table-hover',
        'id'    => 'ninja',
    ],
    /*
     * Default condition to determine if a parameter is a callback or not
     * Callbacks needs to start by those terms or they will be casted to string
     */
    'callback' => ['$', '$.', 'function'],
];
