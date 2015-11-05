<div style="display:none">
    {!! Former::open($entityType . 's/bulk')->addClass('bulk-form') !!}
    {!! Former::text('bulk_action') !!}
    {!! Former::text('bulk_public_id') !!}
    {!! Former::close() !!}
</div>

<script type="text/javascript">
    function submitBulkForm(action, id) {
        if (action == 'delete') {
            if (!confirm('{!! trans("texts.are_you_sure") !!}')) {
                return;
            }
        }

        $('#bulk_public_id').val(id);
        $('#bulk_action').val(action);
        
        $('form.bulk-form').submit();
    }
    function archiveEntity(id) {
        submitBulkForm('archive', id);
    }
    function restoreEntity(id) {
        submitBulkForm('restore', id);
    }
    function deleteEntity(id) {
        submitBulkForm('delete', id);
    }
</script>