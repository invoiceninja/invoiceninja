<table class="table table-striped data-table {{ $class = str_random(8) }}">
    <colgroup>
        @for ($i = 0; $i < count($columns); $i++)
        <col class="con{{ $i }}" />
        @endfor
    </colgroup>
    <thead>
    <tr>
        @foreach($columns as $i => $c)
        <th align="center" valign="middle" class="head{{ $i }}"
            @if ($c == 'checkbox')
                style="width:20px"
            @endif
        >
            @if ($c == 'checkbox' && $hasCheckboxes = true)
                <input type="checkbox" class="selectAll"/>
            @else
                {{ $c }}
            @endif
        </th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($data as $d)
    <tr>
        @foreach($d as $dd)
        <td>{{ $dd }}</td>
        @endforeach
    </tr>
    @endforeach
    </tbody>
</table>
<script type="text/javascript">
    @if (isset($values['clientId']) && $values['clientId'])
            window.load_{{ $values['entityType'] }} = function load_{{ $values['entityType'] }}() {
                load_{{ $class }}();
            }
    @else
        jQuery(document).ready(function(){
            load_{{ $class }}();
        });
    @endif

    function refreshDatatable() {
        window.dataTable.api().ajax.reload();
    }

    function load_{{ $class }}() {
        window.dataTable = jQuery('.{{ $class }}').dataTable({
            "stateSave": true,
            "stateDuration": 0,
            "fnRowCallback": function(row, data) {
                if (data[0].indexOf('ENTITY_DELETED') > 0) {
                    $(row).addClass('entityDeleted');
                }
                if (data[0].indexOf('ENTITY_ARCHIVED') > 0) {
                    $(row).addClass('entityArchived');
                }
            },
            "bAutoWidth": false,
            "aoColumnDefs": [
                @if (isset($hasCheckboxes) && $hasCheckboxes)
                // Disable sorting on the first column
                {
                    'bSortable': false,
                    'aTargets': [ 0, {{ count($columns) - 1 }} ]
                },
                @endif
                {
                    'sClass': 'right',
                    'aTargets': {{ isset($values['rightAlign']) ? json_encode($values['rightAlign']) : '[]' }}
                }
            ],
            @foreach ($options as $k => $o)
            {!! json_encode($k) !!}: {!! json_encode($o) !!},
            @endforeach
            @foreach ($callbacks as $k => $o)
            {!! json_encode($k) !!}: {!! $o !!},
            @endforeach
            "fnDrawCallback": function(oSettings) {
                @if (isset($values['entityType']))
                    if (window.onDatatableReady_{{ $values['entityType'] }}) {
                        window.onDatatableReady_{{ $values['entityType'] }}();
                    } else if (window.onDatatableReady) {
                        window.onDatatableReady();
                    }
                @else
                    if (window.onDatatableReady) {
                        window.onDatatableReady();
                    }
                @endif
            },
            "stateLoadParams": function (settings, data) {
                // don't save filter to local storage
                data.search.search = "";
                // always start on first page of results
                data.start = 0;
            }
        });
    }
</script>
