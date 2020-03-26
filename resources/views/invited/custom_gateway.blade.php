<div class="modal fade" id="custom{{ $number }}GatewayModal" tabindex="-1" role="dialog" aria-labelledby="custom{{ $number }}GatewayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title">{{ $customGateway->getConfigField('name') }}</h4>
            </div>
            <div class="panel-body">
                {!! $customGateway->getCustomHtml($invitation) !!}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }}</button>
            </div>
        </div>
    </div>
</div>
