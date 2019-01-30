<?php

namespace App\Ninja\Datatables;

class ProjectTaskDatatable extends TaskDatatable
{
    public function columns()
    {
        $columns = parent::columns();

        unset($columns[1]);

        return $columns;
    }
}
