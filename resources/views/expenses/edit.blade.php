@extends('header')

@section('head')
    @parent

        @include('money_script')


        <style type="text/css">
            .input-group-addon {
                min-width: 40px;
            }
        </style>
@stop

@section('content')

	{!! Former::open($url)
            ->addClass('warn-on-exit main-form')
            ->onsubmit('return onFormSubmit(event)')
            ->method($method) !!}
    <div style="display:none">
        {!! Former::text('action') !!}
    </div>

	@if ($expense)
		{!! Former::populate($expense) !!}
        {!! Former::populateField('should_be_invoiced', intval($expense->should_be_invoiced)) !!}

        <div style="display:none">
            {!! Former::text('public_id') !!}
            {!! Former::text('invoice_id') !!}
        </div>
	@endif

    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6">

    				{!! Former::select('vendor_id')->addOption('', '')
                            ->data_bind('combobox: vendor_id')
                            ->label(trans('texts.vendor'))
                            ->addGroupClass('vendor-select') !!}

                    {!! Former::select('expense_category_id')->addOption('', '')
                            ->data_bind('combobox: expense_category_id')
                            ->label(trans('texts.category'))
                            ->addGroupClass('category-select') !!}

                    {!! Former::text('expense_date')
                            ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
                            ->addGroupClass('expense_date')
                            ->label(trans('texts.date'))
                            ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}

                    {!! Former::select('expense_currency_id')->addOption('','')
                            ->data_bind('combobox: expense_currency_id')
                            ->label(trans('texts.currency_id'))
                            ->data_placeholder(Utils::getFromCache($account->getCurrencyId(), 'currencies')->name)
                            ->fromQuery($currencies, 'name', 'id') !!}

                    {!! Former::text('amount')
                            ->label(trans('texts.amount'))
                            ->data_bind("value: amount, valueUpdate: 'afterkeydown'")
                            ->addGroupClass('amount')
                            ->append('<span data-bind="html: expenseCurrencyCode"></span>') !!}

                    {!! Former::select('client_id')
                            ->addOption('', '')
                            ->label(trans('texts.client'))
                            ->data_bind('combobox: client_id')
                            ->addGroupClass('client-select') !!}

                    @if (!$expense || ($expense && !$expense->invoice_id && !$expense->client_id))
                        {!! Former::checkbox('should_be_invoiced')
                                ->text(trans('texts.should_be_invoiced'))
                                ->data_bind('checked: should_be_invoiced() || client_id(), enable: !client_id()')
                                ->label(' ') !!}
                    @endif

                    @if (!$expense || ($expense && ! $expense->isExchanged()))
                        {!! Former::checkbox('convert_currency')
                                ->text(trans('texts.convert_currency'))
                                ->data_bind('checked: convert_currency')
                                ->label(' ') !!}
                    @endif


                    <div style="display:none" data-bind="visible: enableExchangeRate">
                        <br/>
                        <span style="display:none" data-bind="visible: !client_id()">
                            {!! Former::select('invoice_currency_id')->addOption('','')
                                    ->label(trans('texts.invoice_currency'))
                                    ->data_placeholder(Utils::getFromCache($account->getCurrencyId(), 'currencies')->name)
                                    ->data_bind('combobox: invoice_currency_id, disable: true')
                                    ->fromQuery($currencies, 'name', 'id') !!}
                        </span>
                        <span style="display:none;" data-bind="visible: client_id">
                            {!! Former::plaintext('test')
                                    ->value('<span data-bind="html: invoiceCurrencyName"></span>')
                                    ->style('min-height:46px')
                                    ->label(trans('texts.invoice_currency')) !!}
                        </span>

                        {!! Former::text('exchange_rate')
                                ->data_bind("value: exchange_rate, enable: enableExchangeRate, valueUpdate: 'afterkeydown'") !!}

                        {!! Former::text('invoice_amount')
                                ->addGroupClass('converted-amount')
                                ->data_bind("value: convertedAmount, enable: enableExchangeRate")
                                ->append('<span data-bind="html: invoiceCurrencyCode"></span>') !!}
                    </div>


                    @if (!$expense || ($expense && (!$expense->tax_name1 && !$expense->tax_name2)))
                        {!! Former::checkbox('apply_taxes')
                                ->text(trans('texts.apply_taxes'))
                                ->data_bind('checked: apply_taxes')
                                ->label(' ') !!}
                    @endif

                    <div style="display:none" data-bind="visible: apply_taxes">
                        <br/>
                        {!! Former::select('tax_select1')
                            ->addOption('','')
                            ->label(trans('texts.tax_rate'))
                            ->onchange('taxSelectChange(event)')
            				->fromQuery($taxRates) !!}

                        <div style="display:none">
                            {!! Former::input('tax_rate1') !!}
                            {!! Former::input('tax_name1') !!}
                        </div>

                        <div style="display:{{ $account->enable_second_tax_rate ? 'block' : 'none' }}">
                            {!! Former::select('tax_select2')
                                ->addOption('','')
                                ->label(trans('texts.tax_rate'))
                                ->onchange('taxSelectChange(event)')
                				->fromQuery($taxRates) !!}

                            <div style="display:none">
                                {!! Former::input('tax_rate2') !!}
                                {!! Former::input('tax_name2') !!}
                            </div>
                        </div>
                    </div>
	            </div>
                <div class="col-md-6">

                    {!! Former::textarea('public_notes')->rows(8) !!}
                    {!! Former::textarea('private_notes')->rows(8) !!}

                </div>
            </div>
            @if ($account->hasFeature(FEATURE_DOCUMENTS))
            <div clas="row">
                <div class="col-md-2 col-sm-4"><div class="control-label" style="margin-bottom:10px;">{{trans('texts.expense_documents')}}</div></div>
                <div class="col-md-12 col-sm-8">
                    <div role="tabpanel" class="tab-pane" id="attached-documents" style="position:relative;z-index:9">
                        <div id="document-upload" class="dropzone">
                            <div data-bind="foreach: documents">
                                <input type="hidden" name="document_ids[]" data-bind="value: public_id"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <center class="buttons">
        {!! Button::normal(trans('texts.cancel'))
                ->asLinkTo(URL::to('/expenses'))
                ->appendIcon(Icon::create('remove-circle'))
                ->large() !!}

        @if (Auth::user()->canCreateOrEdit(ENTITY_EXPENSE, $expense))
            @if (Auth::user()->hasFeature(FEATURE_EXPENSES))
                @if (!$expense || !$expense->is_deleted)
                    {!! Button::success(trans('texts.save'))
                            ->appendIcon(Icon::create('floppy-disk'))
                            ->large()
                            ->submit() !!}
                @endif

                @if ($expense && !$expense->trashed())
                    {!! DropdownButton::normal(trans('texts.more_actions'))
                          ->withContents($actions)
                          ->large()
                          ->dropup() !!}
                @endif

                @if ($expense && $expense->trashed())
                    {!! Button::primary(trans('texts.restore'))
                            ->withAttributes(['onclick' => 'submitAction("restore")'])
                            ->appendIcon(Icon::create('cloud-download'))
                            ->large() !!}
                @endif

            @endif
        @endif
    </center>

	{!! Former::close() !!}

    <script type="text/javascript">
        Dropzone.autoDiscover = false;

        var vendors = {!! $vendors !!};
        var clients = {!! $clients !!};
        var categories = {!! $categories !!};
        var taxRates = {!! $taxRates !!};

        var clientMap = {};
        for (var i=0; i<clients.length; i++) {
            var client = clients[i];
            clientMap[client.public_id] = client;
        }

        function onFormSubmit(event) {
            if (window.countUploadingDocuments > 0) {
                swal("{!! trans('texts.wait_for_upload') !!}");
                return false;
            }

            @if (Auth::user()->canCreateOrEdit(ENTITY_EXPENSE, $expense))
                return true;
            @else
                return false
            @endif
        }

        function onClientChange() {
            var clientId = $('select#client_id').val();
            var client = clientMap[clientId];
            if (client) {
                model.invoice_currency_id(client.currency_id);
            }
        }

        function submitAction(action, invoice_id) {
            $('#action').val(action);
            $('#invoice_id').val(invoice_id);
            $('.main-form').submit();
        }

        function onDeleteClick() {
            sweetConfirm(function() {
                submitAction('delete');
            });
        }

        $(function() {

            var $vendorSelect = $('select#vendor_id');
            for (var i = 0; i < vendors.length; i++) {
                var vendor = vendors[i];
                $vendorSelect.append(new Option(getClientDisplayName(vendor), vendor.public_id));
            }
            $vendorSelect.combobox();

            var $categorySelect = $('select#expense_category_id');
            for (var i = 0; i < categories.length; i++) {
                var category = categories[i];
                $categorySelect.append(new Option(category.name, category.public_id));
            }
            $categorySelect.combobox();

            $('#expense_date').datepicker('update', '{{ $expense ? $expense->expense_date : 'new Date()' }}');

            $('.expense_date .input-group-addon').click(function() {
                toggleDatePicker('expense_date');
            });

            var $clientSelect = $('select#client_id');
            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
                var clientName = getClientDisplayName(client);
                if (!clientName) {
                    continue;
                }
                $clientSelect.append(new Option(clientName, client.public_id));
            }
            $clientSelect.combobox().change(function() {
                onClientChange();
            });

            setTaxSelect(1);
            setTaxSelect(2);

            @if ($data)
                // this means we failed so we'll reload the previous state
                window.model = new ViewModel({!! $data !!});
            @else
                // otherwise create blank model
                window.model = new ViewModel({!! $expense !!});

                ko.applyBindings(model);
            @endif

            @if (!$expense && $clientPublicId)
                onClientChange();
            @endif

            @if (!$vendorPublicId)
                $('.vendor-select input.form-control').focus();
            @else
                $('#amount').focus();
            @endif

            @if (Auth::user()->account->hasFeature(FEATURE_DOCUMENTS))
            $('.main-form').submit(function(){
                if($('#document-upload .fallback input').val())$(this).attr('enctype', 'multipart/form-data')
                else $(this).removeAttr('enctype')
            })

            // Initialize document upload
            dropzone = new Dropzone('#document-upload', {
                url:{!! json_encode(url('documents')) !!},
                params:{
                    _token:"{{ Session::getToken() }}"
                },
                acceptedFiles:{!! json_encode(implode(',',\App\Models\Document::$allowedMimes)) !!},
                addRemoveLinks:true,
                dictRemoveFileConfirmation:"{{trans('texts.are_you_sure')}}",
                @foreach(['default_message', 'fallback_message', 'fallback_text', 'file_too_big', 'invalid_file_type', 'response_error', 'cancel_upload', 'cancel_upload_confirmation', 'remove_file'] as $key)
                    "dict{{strval($key)}}":"{{trans('texts.dropzone_'.Utils::toClassCase($key))}}",
                @endforeach
                maxFilesize:{{floatval(MAX_DOCUMENT_SIZE/1000)}},
            });
            if(dropzone instanceof Dropzone){
                dropzone.on("addedfile",handleDocumentAdded);
                dropzone.on("removedfile",handleDocumentRemoved);
                dropzone.on("success",handleDocumentUploaded);
                dropzone.on("canceled",handleDocumentCanceled);
                dropzone.on("error",handleDocumentError);
                for (var i=0; i<model.documents().length; i++) {
                    var document = model.documents()[i];
                    var mockFile = {
                        name:document.name(),
                        size:document.size(),
                        type:document.type(),
                        public_id:document.public_id(),
                        status:Dropzone.SUCCESS,
                        accepted:true,
                        url:document.url(),
                        mock:true,
                        index:i
                    };

                    dropzone.emit('addedfile', mockFile);
                    dropzone.emit('complete', mockFile);
                    if(document.preview_url()){
                        dropzone.emit('thumbnail', mockFile, document.preview_url()||document.url());
                    }
                    else if(document.type()=='jpeg' || document.type()=='png' || document.type()=='svg'){
                        dropzone.emit('thumbnail', mockFile, document.url());
                    }
                    dropzone.files.push(mockFile);
                }
            }
            @endif
        });

        var ViewModel = function(data) {
            var self = this;

            self.expense_currency_id = ko.observable();
            self.invoice_currency_id = ko.observable();
            self.documents = ko.observableArray();
            self.amount = ko.observable();
            self.exchange_rate = ko.observable(1);
            self.should_be_invoiced = ko.observable();
            self.convert_currency = ko.observable({{ ($expense && $expense->isExchanged()) ? 'true' : 'false' }});
            self.apply_taxes = ko.observable({{ ($expense && ($expense->tax_name1 || $expense->tax_name2)) ? 'true' : 'false' }});

            self.mapping = {
                'documents': {
                    create: function(options) {
                        return new DocumentModel(options.data);
                    }
                }
            }

            if (data) {
                ko.mapping.fromJS(data, self.mapping, this);
            }

            self.account_currency_id = ko.observable({{ $account->getCurrencyId() }});
            self.client_id = ko.observable({{ $clientPublicId }});
            self.vendor_id = ko.observable({{ $vendorPublicId }});
            self.expense_category_id = ko.observable({{ $categoryPublicId }});

            self.convertedAmount = ko.computed({
                read: function () {
                    return roundToTwo(self.amount() * self.exchange_rate()).toFixed(2);
                },
                write: function(value) {
                    self.amount(roundToTwo(value / self.exchange_rate()));
                }
            }, self);


            self.getCurrency = function(currencyId) {
                return currencyMap[currencyId || self.account_currency_id()];
            };

            self.expenseCurrencyCode = ko.computed(function() {
                return self.getCurrency(self.expense_currency_id()).code;
            });

            self.invoiceCurrencyCode = ko.computed(function() {
                return self.getCurrency(self.invoice_currency_id()).code;
            });

            self.invoiceCurrencyName = ko.computed(function() {
                return self.getCurrency(self.invoice_currency_id()).name;
            });

            self.enableExchangeRate = ko.computed(function() {
                if (self.convert_currency()) {
                    return true;
                }
                var expenseCurrencyId = self.expense_currency_id() || self.account_currency_id();
                var invoiceCurrencyId = self.invoice_currency_id() || self.account_currency_id();
                return expenseCurrencyId != invoiceCurrencyId
                    || invoiceCurrencyId != self.account_currency_id()
                    || expenseCurrencyId != self.account_currency_id();
            })

            self.addDocument = function() {
                var documentModel = new DocumentModel();
                self.documents.push(documentModel);
                return documentModel;
            }

            self.removeDocument = function(doc) {
                 var public_id = doc.public_id?doc.public_id():doc;
                 self.documents.remove(function(document) {
                    return document.public_id() == public_id;
                });
            }
        };
        function DocumentModel(data) {
            var self = this;
            self.public_id = ko.observable(0);
            self.size = ko.observable(0);
            self.name = ko.observable('');
            self.type = ko.observable('');
            self.url = ko.observable('');

            self.update = function(data){
                ko.mapping.fromJS(data, {}, this);
            }

            if (data) {
                self.update(data);
            }
        }

        window.countUploadingDocuments = 0;

        function handleDocumentAdded(file){
            // open document when clicked
            if (file.url) {
                file.previewElement.addEventListener("click", function() {
                    window.open(file.url, '_blank');
                });
            }
            if(file.mock)return;
            file.index = model.documents().length;
            model.addDocument({name:file.name, size:file.size, type:file.type});
            window.countUploadingDocuments++;
        }

        function handleDocumentRemoved(file){
            model.removeDocument(file.public_id);
            $.ajax({
                url: '{{ '/documents/' }}' + file.public_id,
                type: 'DELETE',
                success: function(result) {
                    // Do something with the result
                }
            });
        }

        function handleDocumentUploaded(file, response){
            window.countUploadingDocuments--;
            file.public_id = response.document.public_id
            model.documents()[file.index].update(response.document);
            if(response.document.preview_url){
                dropzone.emit('thumbnail', file, response.document.preview_url);
            }
        }

        function handleDocumentCanceled() {
            window.countUploadingDocuments--;
        }

        function handleDocumentError() {
            window.countUploadingDocuments--;
        }

        function taxSelectChange(event) {
            var $select = $(event.target);
            var tax = $select.find('option:selected').text();

            var index = tax.lastIndexOf(': ');
            var taxName =  tax.substring(0, index);
            var taxRate = tax.substring(index + 2, tax.length - 1);

            var selectName = $select.attr('name');
            var instance = selectName.substring(selectName.length - 1);

            $('#tax_name' + instance).val(taxName);
            $('#tax_rate' + instance).val(taxRate);
        }

        function setTaxSelect(instance) {
            var $select = $('#tax_select' + instance);
            var taxName = $('#tax_name' + instance).val();
            var taxRate = $('#tax_rate' + instance).val();
            if (!taxRate || !taxName) {
                return;
            }
            var tax = _.findWhere(taxRates, {name:taxName, rate:taxRate});
            if (tax) {
                $select.val(tax.public_id);
            } else {
                var option = new Option(taxName + ': ' + taxRate + '%', '');
                option.selected = true;
                $select.append(option);
            }
        }


    </script>

@stop
