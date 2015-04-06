<table id="{!! $id !!}" class="{!! $class !!}">
    <colgroup>
        @for ($i = 0; $i < count($columns); $i++)
        <col class="con{!! $i !!}" />
        @endfor
    </colgroup>
    <thead>
    <tr>
        @foreach($columns as $i => $c)
        <th align="center" valign="middle" class="head{!! $i !!}">{!! $c !!}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($data as $d)
    <tr>
        @foreach($d as $dd)
        <td>{!! $dd !!}</td>
        @endforeach
    </tr>
    @endforeach
    </tbody>
</table>

@if (!$noScript)
    @include(Config::get('chumper.datatable.table.script_view'), array('id' => $id, 'options' => $options, 'callbacks' =>  $callbacks))
@endif
