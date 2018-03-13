<?php

namespace App\Ninja\Datatables;

class EntityDatatable
{
    public $entityType;
    public $isBulkEdit;
    public $hideClient;
    public $sortCol = 1;

    public function __construct($isBulkEdit = true, $hideClient = false, $entityType = false)
    {
        $this->isBulkEdit = $isBulkEdit;
        $this->hideClient = $hideClient;

        if ($entityType) {
            $this->entityType = $entityType;
        }
    }

    public function columns()
    {
        return [];
    }

    public function actions()
    {
        return [];
    }

    public function bulkActions()
    {
        return [
            [
                'label' => mtrans($this->entityType, 'archive_'.$this->entityType),
                'url' => 'javascript:submitForm_'.$this->entityType.'("archive")',
            ],
            [
                'label' => mtrans($this->entityType, 'delete_'.$this->entityType),
                'url' => 'javascript:submitForm_'.$this->entityType.'("delete")',
            ],
        ];
    }

    public function columnFields()
    {
        $data = [];
        $columns = $this->columns();

        if ($this->isBulkEdit) {
            $data[] = 'checkbox';
        }

        foreach ($columns as $column) {
            if (count($column) == 3) {
                // third column is optionally used to determine visibility
                if (! $column[2]) {
                    continue;
                }
            }
            $data[] = $column[0];
        }

        $data[] = '';

        return $data;
    }

    public function rightAlignIndices()
    {
        return $this->alignIndices(['amount', 'balance', 'cost']);
    }

    public function centerAlignIndices()
    {
        return $this->alignIndices(['status']);
    }

    public function alignIndices($fields)
    {
        $columns = $this->columnFields();
        $indices = [];

        foreach ($columns as $index => $column) {
            if (in_array($column, $fields)) {
                $indices[] = $index + 1;
            }
        }

        return $indices;
    }

    public function addNote($str, $note) {
        if (! $note) {
            return $str;
        }

        return $str . '&nbsp; <span class="fa fa-file-o" data-toggle="tooltip" data-placement="bottom" title="' . e($note) . '"></span>';
    }

    public function showWithTooltip($str, $max = 60) {
        $str = e($str);

        if (strlen($str) > $max) {
            return '<span data-toggle="tooltip" data-placement="bottom" title="' . mb_substr($str, 0, 500) . '">' . trim(mb_substr($str, 0, $max)) . '...' . '</span>';
        } else {
            return $str;
        }
    }
}
