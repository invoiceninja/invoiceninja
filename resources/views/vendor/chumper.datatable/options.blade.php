@if ($first = true && ($is_obj = false ) == false)
@foreach ( $options as $k => $o )@if ( $first == false ),@endif
@if ($first == true && ($first = false) == false)
    @if(!is_numeric($k))
    {!! '{'; $is_obj = true; !!}
    @else [@endif
@endif
@if (!is_numeric($k)){!! json_encode($k); $obj_parent = true !!}:@endif
@if ( is_string($o))
@if ( @preg_match("#^\s*function\s*\([^\)]*#", $o))
{!! $o !!}@else
{!! json_encode($o) !!}@endif
@else
@if (is_array($o) && ($obj = false) == false)@include(Config::get('chumper.datatable.table.options_view'), array('options' => $o))@else
{!! json_encode($o) !!}@endif 
@endif
@endforeach
@if($is_obj)
}@else]@endif
@endif
