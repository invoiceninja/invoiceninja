<table class="table table-striped {{ $class = str_random(8) }}">
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
    jQuery(document).ready(function(){
        // dynamic table
        jQuery('.{{ $class }}').dataTable({
            // Disable sorting on the first column
            "aaSorting": [],
            "bAutoWidth": false,
            @if (isset($hasCheckboxes) && $hasCheckboxes)
            "aoColumnDefs" : [ {
                'bSortable' : false,
                'aTargets' : [ 0 ]
            } ],
            @endif
            @foreach ($options as $k => $o)
            {{ json_encode($k) }}: {{ json_encode($o) }},
            @endforeach
            @foreach ($callbacks as $k => $o)
            {{ json_encode($k) }}: {{ $o }},
            @endforeach
            "fnDrawCallback": function(oSettings) {
                if (window.onDatatableReady) {
                    window.onDatatableReady();
                }
            }
        });
    });
</script>