{!! Former::vertical_open('/contact_us')->rules([
    'from' => 'required',
    'message' => 'required',
]) !!}

<div class="modal fade" id="contactUsModal" tabindex="-1" role="dialog" aria-labelledby="contactUsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">{{ trans('texts.contact_us') }}</h4>
      </div>

      <div class="modal-body">
      <div class="panel-body">

          {!! Former::plaintext('from')
                ->value(Auth::user()->present()->email) !!}

          {!! Former::textarea('message')
                ->rows(10) !!}

      </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
        <button type="submit" class="btn btn-success" onclick="submitContactUs()">{{ trans('texts.submit') }}</button>
      </div>
    </div>
  </div>
</div>

{!! Former::close() !!}

<script type="text/javascript">

    function showContactUs() {
        $('#contactUsModal').modal('show');
    }

    $(function() {
        $('#contactUsModal').on('shown.bs.modal', function() {
            $("#message").focus();
        })
    })

</script>
