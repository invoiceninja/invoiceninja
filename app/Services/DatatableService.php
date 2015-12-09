<?php namespace App\Services;

use Utils;
use Datatable;

class DatatableService
{
    public function createDatatable($entityType, $query, $columns, $actions = null, $showCheckbox = true)
    {
        $table = Datatable::query($query);
        $orderColumns = [];

        if ($actions && $showCheckbox) {
            $table->addColumn('checkbox', function ($model) {
                return '<input type="checkbox" name="ids[]" value="' . $model->public_id
                        . '" ' . Utils::getEntityRowClass($model) . '>';
            });
        }

        foreach ($columns as $column) {
            // set visible to true by default
            if (count($column) == 2) {
                $column[] = true;
            }

            list($field, $value, $visible) = $column;

            if ($visible) {
                $table->addColumn($field, $value);
                $orderColumns[] = $field;
            }
        }

        if ($actions) {
            $this->createDropdown($entityType, $table, $actions);
        }

        return $table->orderColumns($orderColumns)->make();
    }

    private function createDropdown($entityType, $table, $actions)
    {
        $table->addColumn('dropdown', function ($model) use ($entityType, $actions) {
            $str = '<center>';

            if (property_exists($model, 'is_deleted') && $model->is_deleted) {
                $str .= '<button type="button" class="btn btn-sm btn-danger tr-status" style="display:inline-block; width:100px">'.trans('texts.deleted').'</button>';
            } elseif ($model->deleted_at && $model->deleted_at !== '0000-00-00') {
                $str .= '<button type="button" class="btn btn-sm btn-warning tr-status" style="display:inline-block; width:100px">'.trans('texts.archived').'</button>';
            } else {
                $str .= '<div class="tr-status" style="display:inline-block; width:100px"></div>';
            }

            $str .= '<div class="btn-group tr-action" style="display:none;">
                    <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" style="width:100px">
                        '.trans('texts.select').' <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu" role="menu">';

            $lastIsDivider = false;
            if (!$model->deleted_at || $model->deleted_at == '0000-00-00') {
                foreach ($actions as $action) {
                    if (count($action)) {
                        if (count($action) == 2) {
                            $action[] = function() {
                                return true;
                            };
                        }
                        list($value, $url, $visible) = $action;
                        if ($visible($model)) {
                            $str .= "<li><a href=\"{$url($model)}\">{$value}</a></li>";
                            $lastIsDivider = false;
                        }
                    } elseif ( ! $lastIsDivider) {
                        $str .= "<li class=\"divider\"></li>";
                        $lastIsDivider = true;
                    }
                }

                if ( ! $lastIsDivider) {
                    $str .= "<li class=\"divider\"></li>";
                }

                if ($entityType != ENTITY_USER || $model->public_id) {
                    $str .= "<li><a href=\"javascript:archiveEntity({$model->public_id})\">"
                            . trans("texts.archive_{$entityType}") . "</a></li>";
                }
            } else {
                $str .= "<li><a href=\"javascript:restoreEntity({$model->public_id})\">"
                        . trans("texts.restore_{$entityType}") . "</a></li>";
            }

            if (property_exists($model, 'is_deleted') && !$model->is_deleted) {
                $str .= "<li><a href=\"javascript:deleteEntity({$model->public_id})\">"
                        . trans("texts.delete_{$entityType}") . "</a></li>";
            }

            return $str.'</ul></div></center>';
        });
    }

}