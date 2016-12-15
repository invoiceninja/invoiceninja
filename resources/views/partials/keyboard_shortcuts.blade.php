<style type="text/css">
  .help-panel {
      margin-left: 14px;
      margin-right: 14px;
  }

  .help-panel .col-md-2 div,
  .help-panel .col-md-3 div {
      background-color:#777;
      color:#fff;
      width:28px;
      text-align:center;
      padding-top:2px;
      padding-bottom:2px;
      font-weight:bold;
      font-size: 18px;
      float: left;
      margin-left: 12px;
      margin-top: 4px;
      margin-bottom: 4px;
  }
  .help-panel .key-label {
      padding-top: 10px;
  }
</style>

<div class="modal fade" id="helpModal" tabindex="-1" role="dialog" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title" id="myModalLabel">{{ trans('texts.keyboard_shortcuts') }}</h4>
      </div>
      <div class="panel-body help-panel">
          <div class="row">
              <div class="col-md-3"><div>?</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.help') }}</div>
              <div class="col-md-3"><div>N</div><div>C</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.new_client') }}</div>
          </div>
          <div class="row">
              <div class="col-md-3"><div>/</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.search') }}</div>
              <div class="col-md-3"><div>N</div><div>I</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.new_invoice') }}</div>
          </div>
          <div class="row">
              <div class="col-md-3"><div>M</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.toggle_menu') }}</div>
              <div class="col-md-3"><div>N</div><div>...</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.new_...') }}</div>
          </div>
          <div class="row">
              <div class="col-md-3"><div>H</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.toggle_history') }}</div>
          </div>
          <div class="row">
              <div class="col-md-3"></div>
              <div class="col-md-3"></div>
              <div class="col-md-3"><div>L</div><div>C</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.list_clients') }}</div>
          </div>
          <div class="row">
              <div class="col-md-3"><div>G</div><div>D</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.dashboard') }}</div>
              <div class="col-md-3"><div>L</div><div>I</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.list_invoices') }}</div>
          </div>
          <div class="row">
              <div class="col-md-3"><div>G</div><div>S</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.settings') }}</div>
              <div class="col-md-3"><div>L</div><div>...</div></div>
              <div class="col-md-3 key-label">{{ trans('texts.list_...') }}</div>
          </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }}</button>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">

    function showKeyboardShortcuts() {
        $('#helpModal').modal('show');
    }

    $(function() {

        Mousetrap.bind('?', function(e) {
            showKeyboardShortcuts();
        });

        Mousetrap.bind('/', function(e) {
            event.preventDefault();
            $('#search').focus();
        });

        Mousetrap.bind('g d', function(e) {
            location.href = "{{ url('/dashboard') }}";
        });

        Mousetrap.bind('g s', function(e) {
            location.href = "{{ url('/settings/company_details') }}";
        });

        Mousetrap.bind('h', function(e) {
            $('#right-menu-toggle').trigger('click');
        });

        Mousetrap.bind('m', function(e) {
            $('#left-menu-toggle').trigger('click');
        });

        @foreach([
            'i' => ENTITY_INVOICE,
            'p' => ENTITY_PAYMENT,
            'e' => ENTITY_EXPENSE,
            't' => ENTITY_TASK,
            'c' => ENTITY_CLIENT,
            'q' => ENTITY_QUOTE,
            'v' => ENTITY_VENDOR,
            'r' => ENTITY_RECURRING_INVOICE,
        ] as $key => $value)
            Mousetrap.bind('n {{ $key }}', function(e) {
                location.href = "{{ url($value . 's/create') }}";
            });
            Mousetrap.bind('l {{ $key }}', function(e) {
                location.href = "{{ url($value . 's') }}";
            });
        @endforeach

    });
</script>
