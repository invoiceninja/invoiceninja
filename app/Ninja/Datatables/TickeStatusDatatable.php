<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class TicketStatusDatatable extends EntityDatatable
{
    public $entityType = ENTITY_TICKET;
    public $sortCol = 1;

    public function columns()
    {
        return [
        ];
    }

    public function actions()
    {
        return [];
    }
}
