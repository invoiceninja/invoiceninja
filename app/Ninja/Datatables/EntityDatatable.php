<?php

namespace App\Ninja\Datatables;

/**
 * Class EntityDatatable
 */
class EntityDatatable
{
    public $entityType;

    /**
     * @var bool
     */
    public $isBulkEdit;

    /**
     * @var bool
     */
    public $hideClient;

    /**
     * EntityDatatable constructor.
     *
     * @param bool $isBulkEdit
     * @param bool $hideClient
     */
    public function __construct($isBulkEdit = true, $hideClient = false)
    {
        $this->isBulkEdit = $isBulkEdit;
        $this->hideClient = $hideClient;
    }

    /**
     * @return array
     */
    public function columns()
    {
        return [];
    }

    /**
     * @return array
     */
    public function actions()
    {
        return [];
    }
}
