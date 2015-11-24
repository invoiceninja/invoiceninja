<div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! trans("texts.import_{$entityType}s") !!}</h3>
  </div>
    <div class="panel-body">

    <label for="{{ $entityType }}_header_checkbox">
        <input type="checkbox" name="headers[{{ $entityType }}]" id="{{ $entityType }}_header_checkbox" {{ $hasHeaders ? 'CHECKED' : '' }}> {{ trans('texts.first_row_headers') }}
    </label>

    <p>&nbsp;</p>

    <table class="table invoice-table">
        <thead>
            <tr>
                <th>{{ trans('texts.column') }}</th>
                <th class="col_sample">{{ trans('texts.sample') }}</th>
                <th>{{ trans('texts.import_to') }}</th>
            </tr>   
        </thead>        
    @for ($i=0; $i<count($headers); $i++)
        <tr>
            <td>{{ $headers[$i] }}</td>
            <td class="col_sample">{{ $data[1][$i] }}</td>
            <td>{!! Former::select('map['.$entityType.'][' . $i . ']')->options($columns, $mapped[$i])->raw() !!}</td>
        </tr>
    @endfor
    </table>

    <p>&nbsp;</p>

    <span id="num{{ $entityType }}"></span>

</div>
</div>

<script type="text/javascript">

    $(function() {

        var num{{ $entityType }} = {{ count($data) }};
        function set{{ $entityType }}SampleShown() {
            if ($('#{{ $entityType }}_header_checkbox').is(':checked')) {
                $('.col_sample').show();
                setNum{{ $entityType }}(num{{ $entityType }} - 1);
            } else {
                $('.col_sample').hide();
                setNum{{ $entityType }}(num{{ $entityType }});
            }
        }

        function setNum{{ $entityType }}(num)
        {
            if (num == 1) {
                $('#num{{ $entityType }}').html("1 {{ trans("texts.{$entityType}_will_create") }}");
            } else {
                $('#num{{ $entityType }}').html(num + " {{ trans("texts.{$entityType}s_will_create") }}");
            }
        }

        $('#{{ $entityType }}_header_checkbox').click(set{{ $entityType }}SampleShown);
        set{{ $entityType }}SampleShown();

    });

</script>
