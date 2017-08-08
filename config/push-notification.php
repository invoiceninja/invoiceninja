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
        'apiKey'      =>env('FCM_API_TOKEN'),
        'service'     =>'fcm'
    ]

];
