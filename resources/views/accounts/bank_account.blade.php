@extends('header')

@section('head')
    @parent

    <style type="text/css">
        table.accounts-table > thead > tr > th.header {
            background-color: #e37329 !important;
            color:#fff !important;
            padding-top:8px;
        }

    </style>

@stop

@section('content')
    @parent

    @include('accounts.nav', ['selected' => ACCOUNT_BANKS])

    {!! Former::open($url)
            ->method($method)
            ->rule()
            ->addClass('main-form warn-on-exit') !!}

    <div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{!! trans($title) !!}</h3>
    </div>
    <div class="panel-body form-padding-right">

        @if ($bankAccount)
            {!! Former::populateField('bank_id', $bankAccount->bank_id) !!}
        @endif

        {!! Former::select('bank_id')
                ->data_bind('dropdown: bank_id')
                ->addOption('', '')
                ->fromQuery($banks, 'name', 'id')  !!}

        {!! Former::password('bank_username')
                ->data_bind("value: bank_username, valueUpdate: 'afterkeydown'")
                ->label(trans('texts.username'))
                ->blockHelp(trans(Request::secure() ? 'texts.bank_password_help' : 'texts.bank_password_warning')) !!}

    </div>
    </div>


    <div class="modal fade" id="testModal" tabindex="-1" role="dialog" aria-labelledby="testModalLabel" aria-hidden="true">
      <div class="modal-dialog" style="min-width:150px">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h4 class="modal-title" id="testModalLabel">{!! trans('texts.test_bank_account') !!}</h4>
          </div>

            <div class="panel-body row">
                <div class="form-group" style="padding-bottom:30px">
                    <label for="username" class="control-label col-lg-4 col-sm-4">{{ trans('texts.password') }}</label>
                    <div class="col-lg-6 col-sm-6">
                        <input class="form-control" id="bank_password" name="bank_password" type="password" data-bind="value: bank_password, valueUpdate: 'afterkeydown'"><br/>
                    </div>
                </div>

                <div class="col-lg-12 col-sm-12" data-bind="visible: state() == 'loading'">
                    <div class="progress">
                      <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                      </div>
                    </div>
                </div>

                <div class="col-lg-12 col-sm-12" data-bind="visible: state() == 'error'">
                    <div class="alert alert-danger" role="alert">
                      <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                      {{ trans('texts.bank_account_error') }}
                    </div>
                </div>

                <div class="col-lg-12 col-sm-12" data-bind="visible: bank_accounts().length">
                    <table class="table table-striped accounts-table">
                        <thead>
                            <tr>
                                <th class="header">{{ trans('texts.account_number') }}</th>
                                <th class="header">{{ trans('texts.type') }}</th>
                                <th class="header">{{ trans('texts.balance') }}</th>
                            </tr>
                        </thead>
                        <tbody data-bind="foreach: bank_accounts">
                            <tr>
                                <td data-bind="text: account_number"></td>
                                <td data-bind="text: type"></td>
                                <td data-bind="text: balance"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

          <div class="modal-footer" style="margin-top: 0px; padding-top:30px;">
            <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }}</button>
            <button type="button" class="btn btn-success" onclick="doTest()" data-bind="css: { disabled: disableDoTest }">{{ trans('texts.test') }}</button>
          </div>

        </div>
      </div>
    </div>


    <p/>&nbsp;<p/>

    {!! Former::actions(
        count(Cache::get('banks')) > 0 ? Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/settings/bank_accounts'))->appendIcon(Icon::create('remove-circle')) : false,
        (!$bankAccount ? 
        Button::primary(trans('texts.test'))
            ->withAttributes([
                'data-bind' => 'css: {disabled: disableMainButton}',
                'onclick' => 'showTest()'
            ])
            ->large()
            ->appendIcon(Icon::create('download-alt'))
        : false),
        Button::success(trans('texts.save'))
            ->submit()->large()
            ->withAttributes([
                'data-bind' => 'css: {disabled: disableMainButton}',
            ])
            ->appendIcon(Icon::create('floppy-disk'))) !!}

    {!! Former::close() !!}

    <script type="text/javascript">

    function showTest() {
        $('#testModal').modal('show'); 
    }

    function doTest() {
        model.state('loading');
        $.post('{{ URL::to('/bank_accounts/test') }}', $('.main-form').serialize())
            .done(function(data) {
                model.state('');
                data = JSON.parse(data);
                if (!data || !data.length) {
                    model.state('error');
                } else {
                    for (var i=0; i<data.length; i++) {
                        model.bank_accounts.push(data[i]);
                    }
                }
            }).fail(function() {
                model.state('error');
            });
    }

    $(function() {
        @if ($bankAccount)
            $('#bank_id').prop('disabled', true);
        @else
            $('#bank_id').combobox().on('change', function(e) {
                model.bank_id($('#bank_id').val()); 
            });
        @endif

        $('#testModal').on('shown.bs.modal', function() {
            $('#bank_password').focus();
        });
        
        $('#bank_id').focus();
    });


    var ViewModel = function() {
        var self = this;
        self.bank_id = ko.observable({{ $bankAccount ? $bankAccount->bank_id : 0 }});
        self.bank_username = ko.observable('{{ $bankAccount ? $bankAccount->username : false }}');
        self.bank_password = ko.observable();
        self.bank_accounts = ko.observableArray();

        self.state = ko.observable(false);

        self.disableMainButton = ko.computed(function() {
            return !self.bank_id() || !self.bank_username();
        }, self);

        self.disableDoTest = ko.computed(function() {
            return !self.bank_id() || !self.bank_username() || !self.bank_password();
        }, self);
    };
     
    window.model = new ViewModel();
    ko.applyBindings(model);

    </script>

    
@stop