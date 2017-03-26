<div class="modal fade" id="paymentRefundModal" tabindex="-1" role="dialog" aria-labelledby="paymentRefundModalLabel" aria-hidden="true">
  <div class="modal-dialog" style="min-width:150px">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title" id="paymentRefundModalLabel">{{ trans('texts.refund_payment') }}</h4>
      </div>

      <div class="container" style="width: 100%; padding-bottom: 0px !important">
      <div class="panel panel-default">
      <div class="panel-body">
            <div class="form-horizontal">
              <div class="form-group">
                <label for="refundAmount" class="col-sm-offset-2 col-sm-2 control-label">{{ trans('texts.amount') }}</label>
                <div class="col-sm-4">
                    <div class="input-group">
                            <span class="input-group-addon" id="refundCurrencySymbol"></span>
                        <input type="number" class="form-control" id="refundAmount" name="refund_amount" step="0.01" min="0.01" placeholder="{{ trans('texts.amount') }}">
                    </div>
                    <div class="help-block">{{ trans('texts.refund_max') }} <span id="refundMax"></span></div>
                </div>
              </div>
            </div>
    </div>
    </div>
    </div>

     <div class="modal-footer" style="margin-top: 2px">
        <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
        <button type="button" class="btn btn-primary" id="completeRefundButton">{{ trans('texts.refund') }}</button>
     </div>

    </div>
  </div>
</div>


<script type="text/javascript">
    var paymentId = null;
    function showRefundModal(id, amount, formatted, symbol) {
        paymentId = id;
        $('#refundCurrencySymbol').text(symbol);
        $('#refundMax').text(formatted);
        $('#refundAmount').val(amount).attr('max', amount);
        $('#paymentRefundModal').modal('show');
    }

    function handleRefundClicked(){
        submitForm_payment('refund', paymentId);
    }

    $(function() {
        $('#completeRefundButton').click(handleRefundClicked);
    })

</script>
