<?php

namespace App\Ninja\Datatables;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EntityDatatable
{
    public $entityType;
    public $isBulkEdit;
    public $hideClient;
    public $sortCol = 1;
    public $fieldToSum;

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

    public function ticketIcons($model){

       $iconOutput = '';

        /* Is a agent assigned ? */
        if($model->agent_id > 0)
            $iconOutput .= '<span class="fa fa fa-user" data-toggle="tooltip" data-placement="bottom" title="'. trans('texts.assigned_to') .' '. $model->agent_name.'"></span>&nbsp';
        else
            $iconOutput .= '<span class="fa fa-user-plus" data-toggle="tooltip" data-placement="bottom" title="'. trans('texts.unassigned') .'"></span>&nbsp';

        /* Is the ticket overdue ? */
        if($model->due_date != '0000-00-00 00:00:00' && Carbon::parse($model->due_date) < Carbon::now())
            $iconOutput .= '<span class="fa fa-bomb" data-toggle="tooltip" data-placement="bottom" title="'. trans('texts.alert_ticket_overdue_agent_id') .'"></span>&nbsp';

        /* Is the ticket awaiting a response? */
        if(strlen($model->lastContactByContactKey) > 0)
            $iconOutput .= '<span class="fa fa-envelope" data-toggle="tooltip" data-placement="bottom" title="'. trans('texts.awaiting_reply') .'"></span>&nbsp';

        /* High priority tickets!*/
        if($model->priority_id == TICKET_PRIORITY_HIGH)
            $iconOutput .= '<span class="fa fa-exclamation-triangle" data-toggle="tooltip" data-placement="bottom" title="'. trans('texts.priority') .' : '. trans('texts.high') .'"></span>&nbsp';

        if($model->is_internal)
            $iconOutput .= '<span class="fa fa-group" data-toggle="tooltip" data-placement="bottom" title="'. trans('texts.internal_ticket') .'"></span>&nbsp';

        return $iconOutput;

    }

    public function sumColumn() { return array_search($this->fieldToSum , $this->columnFields()); }
}
