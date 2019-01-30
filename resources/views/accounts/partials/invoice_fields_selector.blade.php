<div class="col-lg-{{ isset($colWidth) ? $colWidth : 3 }} col-md-6">

    {!! Former::select("{$section}_select")
            ->placeholder(trans("texts.{$fields}"))
            ->options($account->getAllInvoiceFields()[$fields])
            ->onchange("addField('{$section}')")
            ->raw() !!}

    <div class="table-responsive">
        <table class="field-list">
        <tbody data-bind="sortable: { data: {{ $section }}, as: 'field', afterMove: onDragged, allowDrop: {{ in_array($section, ['product_fields', 'task_fields']) ? 'false' : 'true' }} }">
            <tr style="cursor:move;background-color:#fff;margin:1px">
                <td>
                    <i class="fa fa-close" style="cursor:default" title="{{ trans('texts.remove') }}"
                        data-bind="click: $root.{{ Utils::toCamelCase('remove' . ucwords($section)) }}"></i>
                    <span data-bind="text: window.field_map[field]"></span>
                </td>
            </tr>
        </tbody>
        </table>
    </div>

</div>
