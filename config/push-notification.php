<?php

return [

    'devNinjaIOS'     => [
        'environment' =>'development',
        'certificate'=>app_path().'/certs/ninjaIOS.pem',
        'passPhrase'  =>'',
        'service'     =>'apns'
    ],
    'ninjaIOS'     => [
        'environment' =>'production',
        'certificate'=>app_path().'/certs/productionNinjaIOS.pem',
        'passPhrase'  =>'',
        'service'     =>'apns'
    ],
    'ninjaAndroid' => [
        'environment' =>'production',
        'apiKey'      =>'yourAPIKey',
        'service'     =>'gcm'
    ]

];