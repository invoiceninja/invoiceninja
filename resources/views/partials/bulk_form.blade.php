<div style="display:none">
    {!! Former::open($entityType . 's/bulk')->addClass("bulk-form bulk-{$entityType}-form") !!}
    {!! Former::text('bulk_action')->addClass('bulk-action') !!}
    {!! Former::text('bulk_public_id')->addClass('bulk-public-id') !!}
    {!! Former::close() !!}
</div>

<script type="text/javascript">
    function submitForm_{{ $entityType }}(action, id) {
        if (action == 'delete') {
            if (!confirm({!! json_encode(trans("texts.are_you_sure")) !!})) {
                return;
            }
        }

        @if (in_array($entityType, [ENTITY_ACCOUNT_GATEWAY]))
            if (action == 'archive') {
                if (!confirm({!! json_encode(trans("texts.are_you_sure")) !!})) {
                    return;
                }
            }
        @endif

        $('.bulk-public-id').val(id);
        $('.bulk-action').val(action);
        $('form.bulk-{{ $entityType }}-form').submit();
    }
</script>
