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
        <h4 class="modal-title" id="myModalLabel">{{ trans('texts.help') }}</h4>
      </div>

      <div class="container" style="width: 100%; padding-bottom: 0px !important">
      <div class="panel panel-default">
      <div class="panel-body help-panel">

          @if (env('SPEECH_ENABLED'))
              <div role="tabpanel">
                  <ul class="nav nav-tabs" role="tablist" style="border: none">
                      <li role="presentation" class="active">
                          <a href="#keyboard_shortcuts" aria-controls="keyboard_shortcuts" role="tab" data-toggle="tab">{{ trans('texts.keyboard_shortcuts') }}</a>
                      </li>
                      <li role="presentation">
                          <a href="#voice_commands" aria-controls="voice_commands" role="tab" data-toggle="tab">{{ trans('texts.voice_commands') }}</a>
                      </li>
                  </ul>
              </div>
              </br>
          @endif

          <div class="tab-content">
              <div role="tabpanel" class="tab-pane active" id="keyboard_shortcuts">

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
                      <div class="col-md-3 key-label">{{ trans('texts.menu') }}</div>
                      <div class="col-md-3"><div>N</div><div>...</div></div>
                      <div class="col-md-3 key-label">{{ trans('texts.new_...') }}</div>
                  </div>
                  <div class="row">
                      <div class="col-md-3"><div>H</div></div>
                      <div class="col-md-3 key-label">{{ trans('texts.history') }}</div>
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

              <div role="tabpanel" class="tab-pane" id="voice_commands">
                  <div class="row">
                      <p>
                          {{ trans('texts.sample_commands') }}:
                      </p>
                      <p>
                          <ul>
                              <li>Go to the dashboard</li>
                              <li>List active and deleted tasks</li>
                              <li>Find &lt;client name&gt;</li>
                              <li>Show me &lt;client name&gt;'s overdue invoices</li>
                              <li>New invoice for &lt;client name&gt;</li>
                              <li>Create payment for invoice &lt;invoice number&gt;</li>
                          </ul>
                      </p>
                      <p>
                          {!! trans('texts.voice_commands_feedback', ['email' => HTML::mailto(CONTACT_EMAIL)]) !!}
                      </p>
                  </div>
              </div>
          </div>

      </div>
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

        var settingsURL = '{{ url('/settings/company_details') }}';
        if (isStorageSupported()) {
            settingsURL = localStorage.getItem('last:settings_page') || settingsURL;
        }
        // if they're on the last viewed settings page link to main settings page
        if ('{{ request()->fullUrl() }}' != settingsURL) {
            $('.nav-settings .nav-link').attr("href", settingsURL);
        }

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

        Mousetrap.bind('g r', function(e) {
            location.href = "{{ url('/reports') }}";
        });

        Mousetrap.bind('g s', function(e) {
            location.href = settingsURL;
        });

        Mousetrap.bind('h', function(e) {
            $('#right-menu-toggle').trigger('click');
        });

        Mousetrap.bind('m', function(e) {
            $('#left-menu-toggle').trigger('click');
        });

        @if (env('SPEECH_ENABLED'))
            Mousetrap.bind('v', function(e) {
                onMicrophoneClick();
            });
        @endif

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

        @foreach([
            'g c d' => 'company_details',
            'g u d' => 'user_details',
            'g l' => 'localization',
            'g o p' => 'online_payments',
            'g t x' => 'tax_rates',
            'g p' => 'products',
            'g n' => 'notifications',
            'g i e' => 'import_export',
            'g a m' => 'account_management',
            'g i s' => 'invoice_settings',
            'g i d' => 'invoice_design',
            'g c p' => 'client_portal',
            'g e' => 'email_settings',
            'g t r' => 'templates_and_reminders',
            'g c c' => 'bank_accounts',
            'g v' => 'data_visualizations',
            'g a t' => 'api_tokens',
            'g u m' => 'user_management',
        ] as $key => $val)
            Mousetrap.bind('{{ $key }}', function(e) {
                location.href = "{!! url('/settings/' . $val) !!}";
            });
        @endforeach


    });
</script>
