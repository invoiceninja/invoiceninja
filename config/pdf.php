<?php

return [

    'phantomjs' => [

        /*
        |--------------------------------------------------------------------------
        | PhantomJS Secret
        |--------------------------------------------------------------------------
        |
        | This enables the PhantomJS request to bypass client authorization.
        |
        */

        'secret' => env('PHANTOMJS_SECRET'),

        /*
        |--------------------------------------------------------------------------
        | PhantomJS Bin Path
        |--------------------------------------------------------------------------
        |
        | The path to the local PhantomJS binary.
        | For example: /usr/local/bin/phantomjs
        | You can run which phantomjs to determine the value
        |
        */

        'bin_path' => env('PHANTOMJS_BIN_PATH'),

        /*
        |--------------------------------------------------------------------------
        | PhantomJS Cloud Key
        |--------------------------------------------------------------------------
        |
        | Key for the https://phantomjscloud.com service
        |
        */

        'cloud_key' => env('PHANTOMJS_CLOUD_KEY')

    ]

];
