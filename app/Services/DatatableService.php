<?php

namespace App\Services;

use App\Ninja\Datatables\EntityDatatable;
use Auth;
use Chumper\Datatable\Table;
use Datatable;
use Utils;

/**
 * Class DatatableService.
 */
class DatatableService
{
    /**
     * @param EntityDatatable $datatable
     * @param $query
     *
     * @throws \Exception
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createDatatable(EntityDatatable $datatable, $query)
    {
        $table = Datatable::query($query);

        if ($datatable->isBulkEdit) {
            $table->addColumn('checkbox', function ($model) {
                $can_edit = Auth::user()->hasPermission('edit_all') || (isset($model->user_id) && Auth::user()->id == $model->user_id);

                return ! $can_edit ? '' : '<input type="checkbox" name="ids[]" value="' . $model->public_id
                        . '" ' . Utils::getEntityRowClass($model) . '>';
            });
        }

        foreach ($datatable->columns() as $column) {
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

        if (count($datatable->actions())) {
            $this->createDropdown($datatable, $table);
        }

        return $table->orderColumns($orderColumns)->make();
    }

    /**
     * @param EntityDatatable $datatable
     * @param Table           $table
     */
    private function createDropdown(EntityDatatable $datatable, $table)
    {
        $table->addColumn('dropdown', function ($model) use ($datatable) {
            $hasAction = false;
            $str = '<center style="min-width:100px">';

            $can_edit = Auth::user()->hasPermission('edit_all') || (isset($model->user_id) && Auth::user()->id == $model->user_id);

            if (property_exists($model, 'is_deleted') && $model->is_deleted) {
                $str .= '<button type="button" class="btn btn-sm btn-danger tr-status">'.trans('texts.deleted').'</button>';
            } elseif ($model->deleted_at && $model->deleted_at !== '0000-00-00') {
                $str .= '<button type="button" class="btn btn-sm btn-warning tr-status">'.trans('texts.archived').'</button>';
            } else {
                $str .= '<div class="tr-status"></div>';
            }

            $dropdown_contents = '';

            $lastIsDivider = false;
            if (! $model->deleted_at || $model->deleted_at == '0000-00-00') {
                foreach ($datatable->actions() as $action) {
                    if (count($action)) {
                        // if show function isn't set default to true
                        if (count($action) == 2) {
                            $action[] = function () {
                                return true;
                            };
                        }
                        list($value, $url, $visible) = $action;
                        if ($visible($model)) {
                            if ($value == '--divider--') {
                                $dropdown_contents .= '<li class="divider"></li>';
                                $lastIsDivider = true;
                            } else {
                                $urlVal = $url($model);
                                $urlStr = is_string($urlVal) ? $urlVal : $urlVal['url'];
                                $attributes = '';
                                if (! empty($urlVal['attributes'])) {
                                    $attributes = ' '.$urlVal['attributes'];
                                }

                                $dropdown_contents .= "<li><a href=\"$urlStr\"{$attributes}>{$value}</a></li>";
                                $hasAction = true;
                                $lastIsDivider = false;
                            }
                        }
                    } elseif (! $lastIsDivider) {
                        $dropdown_contents .= '<li class="divider"></li>';
                        $lastIsDivider = true;
                    }
                }

                if (! $hasAction) {
                    return '';
                }

                if ($can_edit && ! $lastIsDivider) {
                    $dropdown_contents .= '<li class="divider"></li>';
                }

                if (($datatable->entityType != ENTITY_USER || $model->public_id) && $can_edit) {
                    $dropdown_contents .= "<li><a href=\"javascript:submitForm_{$datatable->entityType}('archive', {$model->public_id})\">"
                            . mtrans($datatable->entityType, "archive_{$datatable->entityType}") . '</a></li>';
                }
            } elseif ($can_edit) {
                $dropdown_contents .= "<li><a href=\"javascript:submitForm_{$datatable->entityType}('restore', {$model->public_id})\">"
                    . mtrans($datatable->entityType, "restore_{$datatable->entityType}") . '</a></li>';
            }

            if (property_exists($model, 'is_deleted') && ! $model->is_deleted && $can_edit) {
                $dropdown_contents .= "<li><a href=\"javascript:submitForm_{$datatable->entityType}('delete', {$model->public_id})\">"
                        . mtrans($datatable->entityType, "delete_{$datatable->entityType}") . '</a></li>';
            }

            if (! empty($dropdown_contents)) {
                $str .= '<div class="btn-group tr-action" style="height:auto;display:none">
                    <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" style="width:100px">
                        '.trans('texts.select').' <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu" role="menu">';
                $str .= $dropdown_contents . '</ul>';
            }

            return $str.'</div></center>';
        });
    }
}
