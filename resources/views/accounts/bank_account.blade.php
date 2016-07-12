@extends('header')

@section('head')
    @parent

    @include('money_script')

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

    {!! Former::open()->addClass('main-form warn-on-exit') !!}

    <div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title" data-bind="text: title">&nbsp;</h3>
    </div>
    <div class="panel-body">

        <div data-bind="visible: page() == 'login'">
            <div class="form-padding-right">
                @if ($bankAccount)
                    {!! Former::populateField('public_id', $bankAccount->public_id) !!}
                    {!! Former::hidden('public_id') !!}
                @else
                    {!! Former::select('bank_id')
                            ->data_bind('combobox: bank_id')
                            ->addOption('', '')
                            ->fromQuery($banks, 'name', 'id')
                            ->blockHelp(trans('texts.bank_accounts_help', ['link' => OFX_HOME_URL]))  !!}
                @endif

                {!! Former::password('bank_username')
                        ->data_bind("value: bank_username, valueUpdate: 'afterkeydown'")
                        ->label(trans('texts.username')) !!}

                {!! Former::password('bank_password')
                        ->label(trans('texts.password'))
                        ->data_bind("value: bank_password, valueUpdate: 'afterkeydown'")
                        ->blockHelp(trans(Request::secure() ? 'texts.bank_password_help' : 'texts.bank_password_warning')) !!}
            </div>
        </div>

        <div class="col-lg-12 col-sm-12" data-bind="visible: page() == 'setup'" style="display:none">
            <table class="table accounts-table" style="width:100%">
                <thead>
                    <tr>
                        <th class="header">{{ trans('texts.account_name') }}</th>
                        <th class="header">{{ trans('texts.account_number') }}</th>
                        <th class="header">{{ trans('texts.balance') }}</th>
                        <th class="header">{{ trans('texts.include') }}</th>
                    </tr>
                </thead>
                <tbody data-bind="foreach: bank_accounts">
                    <tr>
                        <td>
                            <input type="text" class="form-control" data-bind="value: account_name, valueUpdate: 'afterkeydown', attr: {name: 'bank_accounts[' + $index() + '][account_name]'}"/>
                            <input type="text" style="display:none" data-bind="value: hashed_account_number, attr: {name: 'bank_accounts[' + $index() + '][hashed_account_number]'}"/>
                        </td>
                        <td data-bind="text: masked_account_number"></td>
                        <td data-bind="text: balance"></td>
                        <td style="text-align:center">
                            <input type="checkbox" value="1"
                                data-bind="checked: includeAccount, attr: {name: 'bank_accounts[' + $index() + '][include]'}"/>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>


        <div class="col-lg-12 col-sm-12" data-bind="visible: page() == 'import'" style="display:none">
            <div class="row panel">
                <div class="col-md-8" style="height:60px;padding-top:10px;">
                    <span data-bind="text: statusLabel"></span>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" data-bind="value: $root.filter, valueUpdate: 'afterkeydown'"
                        placeholder="{{ trans('texts.filter') }}"/>
                </div>
            </div>

            <div data-bind="foreach: bank_accounts">

                <h4 data-bind="text: account_name"></h4><br/>

                <table class="table accounts-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th class="header">
                                <input type="checkbox" data-bind="checked: checkall, click: updateChecked"/>
                            </th>
                            <th class="header">{{ trans('texts.vendor') }}</th>
                            <th class="header">{{ trans('texts.info') }}</th>
                            <th class="header">{{ trans('texts.public_notes') }}</th>
                            <th class="header" nowrap>{{ trans('texts.date') }}</th>
                            <th class="header" nowrap>{{ trans('texts.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody data-bind="foreach: filteredTransactions">
                        <tr>
                            <td style="text-align:center">
                                <input type="checkbox" value="1"
                                    data-bind="checked: includeTransaction, attr: {name: 'bank_accounts[' + $index() + '][include]'}"/>
                            </td>
                            <td>
                                <input type="text" class="form-control"
                                    data-bind="value: vendor.pretty, valueUpdate: 'afterkeydown'"/>
                            </td>
                            <td>
                                <input type="text" class="form-control"
                                    data-bind="value: info, valueUpdate: 'afterkeydown'"/>
                            </td>
                            <td>
                                <input type="text" class="form-control"
                                    data-bind="value: memo, valueUpdate: 'afterkeydown'"/>
                            </td>
                            <td data-bind="text: date" nowrap></td>
                            <td data-bind="text: amount.pretty" nowrap></td>
                        </tr>
                    </tbody>
                </table>
                <p>&nbsp;</p>
            </div>
        </div>

        <div class="col-lg-12 col-sm-12" data-bind="visible: page() == 'done'" style="display:none">
            <div class="alert alert-info" role="alert">
              <span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
              <span data-bind="text: importResults()"></span>
            </div>
        </div>

        <div class="col-lg-12 col-sm-12" data-bind="visible: isLoading" style="display:none">
            <p>&nbsp;</p>
            <div class="progress">
              <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
              </div>
            </div>
        </div>

        <div class="col-lg-12 col-sm-12" data-bind="visible: errorStr" style="display:none">
            <div class="alert alert-danger" role="alert">
              <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                  {{ trans('texts.bank_account_error') }}
            </div>
        </div>

    </div>
    </div>

    <p/>&nbsp;<p/>

    @if (Auth::user()->hasFeature(FEATURE_EXPENSES))
        {!! Former::actions(
            count(Cache::get('banks')) > 0 ?
                Button::normal(trans('texts.cancel'))
                    ->withAttributes([
                        'data-bind' => 'visible: !importResults()',
                    ])
                    ->large()
                    ->asLinkTo(URL::to('/settings/bank_accounts'))
                    ->appendIcon(Icon::create('remove-circle')) : false,
            Button::success(trans('texts.validate'))
                ->withAttributes([
                    'data-bind' => 'css: {disabled: disableValidate}, visible: page() == "login"',
                    'onclick' => 'validate()'
                ])
                ->large()
                ->appendIcon(Icon::create('lock')),
            Button::success(trans('texts.save'))
                ->withAttributes([
                    'data-bind' => 'css: {disabled: disableSave}, visible: page() == "setup"',
                    'style' => 'display:none',
                    'onclick' => 'save()'
                ])
                ->large()
                ->appendIcon(Icon::create('floppy-disk'))   ,
            Button::success(trans('texts.import'))
                ->withAttributes([
                    'data-bind' => 'css: {disabled: disableSaveExpenses}, visible: page() == "import"',
                    'style' => 'display:none',
                    'onclick' => 'saveExpenses()'
                ])
                ->large()
                ->appendIcon(Icon::create('floppy-disk'))) !!}
    @endif
    
    {!! Former::close() !!}

    <script type="text/javascript">

    function validate() {
        model.errorStr(false);
        model.isLoading(true);
        $.post('{{ URL::to('/bank_accounts/validate') }}', $('.main-form').serialize())
            .done(function(data) {
                data = JSON.parse(data);
                if (!data || !data.length) {
                    model.isLoading(false);
                    model.errorStr('error');
                } else {
                    loadTransactions(data);
                    @if ($bankAccount)
                        model.setPage('import')
                    @else
                        model.setPage('setup')
                    @endif
                }
            }).fail(function() {
                model.isLoading(false);
                model.errorStr('error');
            });
    }

    function save() {
        model.errorStr(false);
        model.isLoading(true);
        $.post('{{ URL::to('/bank_accounts') }}', $('.main-form').serialize())
            .done(function(data) {
                data = JSON.parse(data);
                if (!data || !data.length) {
                    model.isLoading(false);
                    model.errorStr('error');
                } else {
                    loadTransactions(data);
                    model.setPage('import')
                }
            }).fail(function() {
                model.isLoading(false);
                model.errorStr('error');
            });
    }

    function loadTransactions(data) {
        model.bank_accounts.removeAll();
        for (var i=0; i<data.length; i++) {
            var row = data[i];
            var account = new BankAccountModel(row);
            if (row.transactions) {
                var transactions = account.transactions();
                for (var j=0; j<row.transactions.length; j++) {
                    var transaction = row.transactions[j];
                    transactions.push(new TransactionModel(transaction));
                }
                account.transactions.valueHasMutated();
            }
            model.bank_accounts.push(account);
        }
    }

    function saveExpenses() {
        model.errorStr(false);
        model.isLoading(true);

        $.ajax({
            url: '{{ URL::to('/bank_accounts/import_expenses') }}' + '/' + model.bank_id(),
            type: 'POST',
            data: ko.toJSON(model.includedTransactions()),
            datatype: 'json',
            processData: false,
            contentType: 'application/json; charset=utf-8',
            success: function (result) {
                NINJA.formIsChanged = false;
                model.importResults(result);
                model.setPage('done');
            }
        });
    }

    $(function() {
        $('#bank_id').focus();
    });

    var TransactionModel = function(data) {
        var self = this;
        self.vendor = ko.observable(data.vendor);
        self.info = ko.observable(data.info);
        self.memo = ko.observable(data.memo);
        self.amount = ko.observable(data.amount);
        self.date = ko.observable(data.date);
        self.vendor_orig = data.vendor;
        self.id = data.uniqueId;

        self.includeTransaction = ko.observable();

        self.vendor.pretty = ko.computed({
            read: function () {
                return self.vendor();
            },
            write: function (value) {
                this.vendor(value);
                for (var i=0; i<model.bank_accounts().length; i++) {
                    var account = model.bank_accounts()[i];
                    var transactions = account.transactions();
                    for (var j=0; j<transactions.length; j++) {
                        var transaction = transactions[j];
                        if (transaction.vendor_orig == this.vendor_orig) {
                            transaction.vendor(value);
                        }
                    }
                }

            },
            owner: self
        })

        self.amount.pretty = ko.computed({
            read: function () {
                return self.amount() ? formatMoney(self.amount()) : '';
            },
            write: function (value) {
                this.amount(value);
            },
            owner: self
        });

        self.isMatch = function(filter) {
            var filter = filter.toLowerCase();
            var values = [
                self.vendor(),
                self.info(),
                self.memo()
            ];
            for (var i=0; i<values.length; i++) {
                if (values[i] && values[i].toLowerCase().indexOf(filter) >= 0) {
                    return true;
                }
            }
            return false;
        }
    }

    var BankAccountModel = function(data) {
        var self = this;
        self.includeAccount = ko.observable(true);
        self.checkall = ko.observable();
        self.hashed_account_number = ko.observable(data.hashed_account_number);
        self.masked_account_number = ko.observable(data.masked_account_number);
        self.account_name = ko.observable(data.account_name);
        self.balance = ko.observable(data.balance);
        self.transactions = ko.observableArray();

        self.filteredTransactions = ko.computed(function() {
            if (!model.filter()) {
                return self.transactions();
            } else {
                return ko.utils.arrayFilter(self.transactions(), function(transaction) {
                    return transaction.isMatch(model.filter());
                });
            }
        }, self).extend({ rateLimit: { timeout: 350, method: 'notifyWhenChangesStop' } });


        self.isValid = ko.computed(function() {
            return self.account_name() ? true : false;
        }, self);

        self.updateChecked = function() {
            var data = self.filteredTransactions();
            for (var i=0; i<data.length; i++) {
                data[i].includeTransaction(self.checkall());
            }
            self.transactions.valueHasMutated();
            return true;
        }
    }

    var ViewModel = function() {
        var self = this;
        self.bank_id = ko.observable({{ $bankAccount ? $bankAccount->bank_id : 0 }});
        self.bank_username = ko.observable('{{ $bankAccount ? $bankAccount->username : false }}');
        self.bank_password = ko.observable();
        self.bank_accounts = ko.observableArray();

        self.page = ko.observable();
        self.title = ko.observable();
        self.errorStr = ko.observable(false);
        self.isLoading = ko.observable(false);
        self.importResults = ko.observable();
        self.filter = ko.observable();

        self.setPage = function(page) {
            self.isLoading(false);
            self.page(page);
            if (page == 'login') {
                @if ($bankAccount)
                    self.title("{{ $bankAccount->bank->name }}");
                @else
                    self.title("{{ trans('texts.add_bank_account') }}");
                @endif
            } else if (page == 'setup') {
                self.title("{{ trans('texts.setup_account') }}");
            } else if (page == 'import') {
                self.title("{{ trans('texts.import_expenses') }}");
            }
        }
        self.setPage('login')

        self.includedTransactions = ko.computed(function() {
            var data = [];
            for (var i=0; i<self.bank_accounts().length; i++) {
                var account = self.bank_accounts()[i];
                var transactions = ko.utils.arrayFilter(account.transactions(), function(transaction) {
                    return transaction.includeTransaction();
                });
                data = data.concat(transactions);
            }
            return data;
        });

        self.countExpenses = ko.computed(function() {
            var count = 0;
            for (var i=0; i<self.bank_accounts().length; i++) {
                var account = self.bank_accounts()[i];
                var transactions = ko.utils.arrayFilter(account.transactions(), function(transaction) {
                    return transaction.includeTransaction();
                });
                count += transactions.length;
            }
            return count;
        });

        self.statusLabel = ko.computed(function() {
            var count = 0;
            var total = 0;
            for (var i=0; i<self.bank_accounts().length; i++) {
                var account = self.bank_accounts()[i];
                var transactions = ko.utils.arrayFilter(account.transactions(), function(transaction) {
                    return transaction.includeTransaction();
                });
                count += transactions.length;
                for (var j=0; j<transactions.length; j++) {
                    total += transactions[j].amount();
                }
            }
            var str = count + (count == 1 ? " {{ trans('texts.expense') }}" : " {{ trans('texts.expenses') }}") + " | ";
            return str + formatMoney(total);
        });

        self.disableValidate = ko.computed(function() {
            if (self.isLoading()) {
                return true;
            }
            return !self.bank_id() || !self.bank_username() || !self.bank_password();
        }, self);

        self.disableSave = ko.computed(function() {
            if (self.disableValidate()) {
                return true;
            }
            var hasAccount = false;
            for (var i=0; i<self.bank_accounts().length; i++) {
                var account = self.bank_accounts()[i];
                if (account.includeAccount()) {
                    if (account.isValid()) {
                        hasAccount = true;
                    } else {
                        return true;
                    }
                }
            }
            return !hasAccount;
        }, self);

        self.disableSaveExpenses = ko.computed(function() {
            if (self.isLoading()) {
                return true;
            }
            return self.countExpenses() == 0;
        }, self);
    };

    window.model = new ViewModel();
    ko.applyBindings(model);

    @if (!empty($transactions))
        loadTransactions({!! $transactions !!});
        model.setPage('import');
    @endif

    </script>


@stop
