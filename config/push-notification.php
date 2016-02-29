<?php

return [

    'devNinjaIOS'     => array(
        'environment' =>'development',
        'certificate'=>app_path().'/certs/ninjaIOS.pem',
        'passPhrase'  =>'',
        'service'     =>'apns'
    ),
    'ninjaIOS'     => array(
        'environment' =>'production',
        'certificate'=>app_path().'/certs/productionNinjaIOS.pem',
        'passPhrase'  =>'',
        'service'     =>'apns'
    ),
    'ninjaAndroid' => array(
        'environment' =>'production',
        'apiKey'      =>'yourAPIKey',
        'service'     =>'gcm'
    )

];