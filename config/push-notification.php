<?php

return [

    'devNinjaIOS'     => [
        'environment' =>'development',
        'certificate' =>storage_path().'/ninjaIOS.pem',
        'passPhrase'  =>'',
        'service'     =>'apns'
    ],
    'ninjaIOS'     => [
        'environment' =>'production',
        'certificate' =>storage_path().'/productionNinjaIOS.pem',
        'passPhrase'  =>'',
        'service'     =>'apns'
    ],
    'ninjaAndroid' => [
        'environment' =>'production',
        'apiKey'      =>'yourAPIKey',
        'service'     =>'gcm'
    ]

];
