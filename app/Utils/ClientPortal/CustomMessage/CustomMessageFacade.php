<?php

namespace App\Utils\ClientPortal\CustomMessage;

use Illuminate\Support\Facades\Facade;

class ClientPortalFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'customMessage';
    }
}
