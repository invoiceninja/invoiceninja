<?php namespace App\Ninja\Datatables;

class EntityDatatable
{
    public $entityType;
    public $isBulkEdit;
    public $hideClient;

    public function __construct($isBulkEdit = true, $hideClient = false)
    {
        $this->isBulkEdit = $isBulkEdit;
        $this->hideClient = $hideClient;
    }

    public function columns()
    {
        return [];
    }

    public function actions()
    {
        return [];
    }
}
