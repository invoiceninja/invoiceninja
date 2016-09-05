<div class="col-md-3">

    {!! Former::select("{$section}_select")
            ->placeholder(trans("texts.{$fields}"))
            ->options($account->getAllInvoiceFields()[$fields])
            ->onchange("addField('{$section}')")
            ->raw() !!}

    <table class="field-list">
    <tbody data-bind="sortable: { data: {{ $section }}, as: 'field' }">
        <tr>
            <td>
                <i class="fa fa-close" data-bind="click: $root.{{ Utils::toCamelCase('remove' . ucwords($section)) }}"></i>
                <div data-bind="text: window.field_map[field]"></div>
                <i class="fa fa-bars"></i>
            </td>
        </tr>
    </tbody>
    </table>


</div>
