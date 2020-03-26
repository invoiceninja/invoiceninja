<script type="text/javascript">
    jQuery(document).ready(function(){
        // dynamic table
        oTable = jQuery('#{!! $id !!}').dataTable({
@if ($first = true)
@foreach ( $options as $k => $o )@if ( $first == false ),@endif
@if (($first = false) == false &&!is_numeric($k)){!! json_encode($k); $obj_parent = true !!}:@endif
@if ( is_string($o))
@if ( @preg_match("#^\s*function\s*\([^\)]*#", $o))
{!! $o !!}@else
{!! json_encode($o) !!}@endif
@else
@if (is_array($o) && ($obj = false) == false)@include(Config::get('chumper.datatable.table.options_view'), array('options' => $o))@else
{!! json_encode($o) !!}@endif 
@endif
@endforeach
@endif
@if ( $first == false ),@endif
@foreach ($callbacks as $k => $o) {!! json_encode($k) !!}: {!! $o !!}
@endforeach
});
    });
</script>
