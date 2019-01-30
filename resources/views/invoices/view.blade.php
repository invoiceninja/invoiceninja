@extends('public.header')

@section('head')
	@parent

		@include('money_script')

		@foreach ($invoice->client->account->getFontFolders() as $font)
        	<script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    	@endforeach

        <script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

		@if ($account->showSignature($invoice))
			<script src="{{ asset('js/jSignature.min.js') }}"></script>
		@endif

		<style type="text/css">
			body {
				background-color: #f8f8f8;
			}

            .dropdown-menu li a{
                overflow:hidden;
                margin-top:5px;
                margin-bottom:5px;
            }

			#signature {
		        border: 2px dotted black;
		        background-color:lightgrey;
		    }
		</style>

    @if (!empty($transactionToken) && $accountGateway->gateway_id == GATEWAY_BRAINTREE && $accountGateway->getPayPalEnabled())
        <div id="paypal-container"></div>
        <script type="text/javascript" src="https://js.braintreegateway.com/js/braintree-2.23.0.min.js"></script>
        <script type="text/javascript" >
            $(function() {
                var paypalLink = $('a[href$="paypal"]'),
                    paypalUrl = paypalLink.attr('href'),
                    checkout;
                paypalLink.parent().attr('id', 'paypal-container');
                braintree.setup("{{ $transactionToken }}", "custom", {
                    onReady: function (integration) {
                        checkout = integration;
                        $('a[href$="#braintree_paypal"]').each(function(){
                            var el=$(this);
                            el.attr('href', el.attr('href').replace('#braintree_paypal','?device_data='+encodeURIComponent(integration.deviceData)))
                        })
                    },
                    paypal: {
                        container: "paypal-container",
                        singleUse: false,
                        enableShippingAddress: false,
                        enableBillingAddress: false,
                        headless: true,
                        locale: "{{ $invoice->client->language ? $invoice->client->language->locale : $invoice->account->language->locale }}"
                    },
                    dataCollector: {
                        paypal: true
                    },
                    onPaymentMethodReceived: function (obj) {
                        window.location.href = paypalUrl.replace('#braintree_paypal', '') + '/' + encodeURIComponent(obj.nonce) + "?device_data=" + encodeURIComponent(JSON.stringify(obj.details));
                    }
                });
                paypalLink.click(function(e){
                    e.preventDefault();
					@if ($account->requiresAuthorization($invoice))
						window.pendingPaymentFunction = checkout.paypal.initAuthFlow;
						showAuthorizationModal();
					@else
                    	checkout.paypal.initAuthFlow();
					@endif
                })
            });
        </script>
    @elseif(!empty($enableWePayACH))
        <script type="text/javascript" src="https://static.wepay.com/js/tokenization.v2.js"></script>
        <script type="text/javascript">
			function payWithWepay() {
				var achLink = $('a[href$="/bank_transfer"]');
				$('#wepay-error').remove();
				var email = {!! json_encode($contact->email) !!} || prompt('{{ trans('texts.ach_email_prompt') }}');
				if (!email) {
					return;
				}

				WePay.bank_account.create({
					'client_id': '{{ WEPAY_CLIENT_ID }}',
					'email':email
				}, function(data){
					dataObj = JSON.parse(data);
					if(dataObj.bank_account_id) {
						window.location.href = achLink.attr('href') + '/' + dataObj.bank_account_id + "?details=" + encodeURIComponent(data);
					} else if(dataObj.error) {
						$('#wepay-error').remove();
						achLink.closest('.container').prepend($('<div id="wepay-error" style="margin-top:20px" class="alert alert-danger"></div>').text(dataObj.error_description));
					}
				});
			}

            $(function() {
                var achLink = $('a[href$="/bank_transfer"]');
                WePay.set_endpoint('{{ WEPAY_ENVIRONMENT }}');
				achLink.click(function(e) {
                	e.preventDefault();
					@if ($account->requiresAuthorization($invoice))
						window.pendingPaymentFunction = window.payWithWepay;
						showAuthorizationModal();
					@else
                    	payWithWepay();
					@endif
                });
            });
        </script>
	@elseif (! empty($accountGateway) && $accountGateway->getApplePayEnabled())
		<script type="text/javascript" src="https://js.stripe.com/v3/"></script>
	    <script type="text/javascript">
	        // https://stripe.com/docs/stripe-js/elements/payment-request-button
	        var stripe = Stripe('{{ $accountGateway->getPublishableKey() }}');
	        var paymentRequest = stripe.paymentRequest({
	            country: '{{ $invoice->client->getCountryCode() }}',
	            currency: '{{ strtolower($invoice->client->getCurrencyCode()) }}',
	            total: {
	                label: '{{ trans('texts.invoice') . ' ' . $invitation->invoice->invoice_number }}',
	                amount: {{ $invitation->invoice->getRequestedAmount() * 100 }},
	            },
	        });

	        var elements = stripe.elements();
	        var prButton = elements.create('paymentRequestButton', {
	            paymentRequest: paymentRequest,
	        });

	        $(function() {
	            // Check the availability of the Payment Request API first.
	            paymentRequest.canMakePayment().then(function(result) {
	                if (! result) {
						$('#paymentButtons ul.dropdown-menu li').find('a[href$="apple_pay"]').remove();
	                }
	            });

	        });

	    </script>

	@endif
@stop

@section('content')

	<div class="container">

		@if ($message = $invoice->client->customMessage($invoice->getCustomMessageType()))
			@include('invited.custom_message', ['message' => $message])
        @endif

        @if (!empty($partialView))
            @include($partialView)
        @else
            <div id="paymentButtons" class="pull-right" style="text-align:right">
            @if ($invoice->isQuote())
                {!! Button::normal(trans('texts.download'))->withAttributes(['onclick' => 'onDownloadClick()'])->large() !!}&nbsp;&nbsp;
                @if ($showApprove)
                    {!! Button::success(trans('texts.approve'))->withAttributes(['id' => 'approveButton', 'onclick' => 'onApproveClick()', 'class' => 'require-authorization'])->large() !!}
				@elseif ($invoiceLink = $invoice->getInvoiceLinkForQuote($contact->id))
					{!! Button::success(trans('texts.view_invoice'))->asLinkTo($invoiceLink)->large() !!}
                @endif
			@elseif ( ! $invoice->canBePaid())
				{!! Button::normal(trans('texts.download'))->withAttributes(['onclick' => 'onDownloadClick()'])->large() !!}
    		@elseif ($invoice->client->account->isGatewayConfigured() && floatval($invoice->balance) && !$invoice->is_recurring)
                {!! Button::normal(trans('texts.download'))->withAttributes(['onclick' => 'onDownloadClick()'])->large() !!}&nbsp;&nbsp;
				<span class="require-authorization">
	                @if (count($paymentTypes) > 1)
	                    {!! DropdownButton::success(trans('texts.pay_now'))->withContents($paymentTypes)->large() !!}
	                @elseif (count($paymentTypes) == 1)
	                    <a href='{{ $paymentURL }}' class="btn btn-success btn-lg">{{ trans('texts.pay_now') }} {!! $invoice->present()->gatewayFee($gatewayTypeId) !!}</a>
	                @endif
				</span>
    		@else
    			{!! Button::normal(trans('texts.download'))->withAttributes(['onclick' => 'onDownloadClick()'])->large() !!}
    		@endif

			@if ($account->isNinjaAccount())
				{!! Button::primary(trans('texts.return_to_app'))->asLinkTo(URL::to('/settings/account_management'))->large() !!}
			@endif
    		</div>
        @endif

        <div class="pull-left">
            @if(!empty($documentsZipURL))
                {!! Button::normal(trans('texts.download_documents', array('size'=>Form::human_filesize($documentsZipSize))))->asLinkTo($documentsZipURL)->large() !!}
            @endif
        </div>

		<div class="clearfix"></div><p>&nbsp;</p>
        @if ($account->isPro() && $invoice->hasDocuments())
            <div class="invoice-documents">
            <h3>{{ trans('texts.documents_header') }}</h3>
            <ul>
            @foreach ($invoice->allDocuments() as $document)
                <li><a target="_blank" href="{{ $document->getClientUrl($invitation) }}">{{$document->name}} ({{Form::human_filesize($document->size)}})</a></li>
            @endforeach
            </ul>
            </div>
        @endif

        @if ($account->hasFeature(FEATURE_DOCUMENTS) && $account->invoice_embed_documents)
            @foreach ($invoice->documents as $document)
                @if($document->isPDFEmbeddable())
                    <script src="{{ $document->getClientVFSJSUrl() }}" type="text/javascript" async></script>
                @endif
            @endforeach
            @foreach ($invoice->expenses as $expense)
                @foreach ($expense->documents as $document)
                    @if($document->isPDFEmbeddable())
                        <script src="{{ $document->getClientVFSJSUrl() }}" type="text/javascript" async></script>
                    @endif
                @endforeach
            @endforeach
        @endif
		<script type="text/javascript">

			window.invoice = {!! $invoice !!};
			invoice.features = {
                customize_invoice_design:{{ $invoice->client->account->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ? 'true' : 'false' }},
                remove_created_by:{{ $invoice->client->account->hasFeature(FEATURE_REMOVE_CREATED_BY) ? 'true' : 'false' }},
                invoice_settings:{{ $invoice->client->account->hasFeature(FEATURE_INVOICE_SETTINGS) ? 'true' : 'false' }}
            };
			invoice.is_quote = {{ $invoice->isQuote() ? 'true' : 'false' }};
			invoice.contact = {!! $contact !!};

			function getPDFString(cb) {
    	  	    return generatePDF(invoice, invoice.invoice_design.javascript, true, cb);
			}

            if (window.hasOwnProperty('pjsc_meta')) {
                window['pjsc_meta'].remainingTasks++;
            }

			function waitForSignature() {
				if (window.signatureAsPNG || ! invoice.invitations[0].signature_base64) {
					writePdfAsString();
				} else {
					window.setTimeout(waitForSignature, 100);
				}
			}

			function writePdfAsString() {
				doc = getPDFString();
				doc.getDataUrl(function(pdfString) {
					document.write(pdfString);
					document.close();
					if (window.hasOwnProperty('pjsc_meta')) {
						window['pjsc_meta'].remainingTasks--;
					}
				});
			}

			$(function() {
                @if (Input::has('phantomjs'))
					@if (Input::has('phantomjs_balances'))
						document.write(calculateAmounts(invoice).total_amount);
						document.close();
						if (window.hasOwnProperty('pjsc_meta')) {
							window['pjsc_meta'].remainingTasks--;
						}
					@else
						@if ($account->signature_on_pdf)
							refreshPDF();
							waitForSignature();
						@else
							writePdfAsString();
						@endif
					@endif
                @else
                    refreshPDF();
                @endif

				@if ($account->requiresAuthorization($invoice))
					$('.require-authorization a').on('click', function(e) {
						e.preventDefault();
						window.pendingPaymentHref = $(this).attr('href');
						showAuthorizationModal();
					});

					@if ($account->showSignature($invoice))
						$('#authorizationModal').on('shown.bs.modal', function () {
							if ( ! window.pendingPaymentInit) {
								window.pendingPaymentInit = true;
								$("#signature").jSignature().bind('change', function(e) {
									setModalPayNowEnabled();
								});;
							}
						});
					@endif
				@endif
			});

			function showAuthorizationModal() {
				@if ($account->showSignature($invoice))
					if (window.pendingPaymentInit) {
						$("#signature").jSignature('reset');
					}
				@endif
				@if ($account->showAcceptTerms($invoice))
					$('#termsCheckbox').attr('checked', false);
				@endif
				$('#authorizationModal').modal('show');
			}

			function onApproveClick() {
				@if ($account->requiresAuthorization($invoice))
					window.pendingPaymentFunction = approveQuote;
					showAuthorizationModal();
				@else
					approveQuote();
				@endif
			}

			function approveQuote() {
				$('#approveButton').prop('disabled', true);
				location.href = "{{ url('/approve/' . $invitation->invitation_key) }}";
			}

			function onDownloadClick() {
				try {
					var doc = generatePDF(invoice, invoice.invoice_design.javascript, true);
	                var fileName = invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice;
					doc.save(fileName + '_' + invoice.invoice_number + '.pdf');
			    } catch (exception) {
					if (location.href.indexOf('/view/') > 0) {
			            location.href = location.href.replace('/view/', '/download/');
			        }
				}
			}

			function showCustom1Modal() {
                $('#custom1GatewayModal').modal('show');
            }

			function showCustom2Modal() {
                $('#custom2GatewayModal').modal('show');
            }

			function showCustom3Modal() {
                $('#custom3GatewayModal').modal('show');
            }

			function onModalPayNowClick() {
				@if ($account->showSignature($invoice))
					var data = {
						signature: $('#signature').jSignature('getData', 'svgbase64')[1]
					};
				@else
					var data = false;
				@endif
				$.ajax({
				    url: "{{ URL::to('authorize/' . $invitation->invitation_key) }}",
				    type: 'PUT',
					data: data,
				    success: function(response) {
				 		redirectToPayment();
				    },
					error: function(response) {
						alert("{{ trans('texts.error_refresh_page') }}");
					}
				});
			}

			function redirectToPayment() {
				$('#authorizationModal').modal('hide');
				if (window.pendingPaymentFunction) {
					window.pendingPaymentFunction();
				} else {
					location.href = window.pendingPaymentHref;
				}
			}

			function setModalPayNowEnabled() {
				var disabled = false;

				@if ($account->showAcceptTerms($invoice))
					if ( ! $('#termsCheckbox').is(':checked')) {
						disabled = true;
					}
				@endif

				@if ($account->showSignature($invoice))
					if ( ! $('#signature').jSignature('isModified')) {
						disabled = true;
					}
				@endif

				$('#modalPayNowButton').attr('disabled', disabled);
			}


		</script>

		@include('invoices.pdf', ['account' => $invoice->client->account])

		<p>&nbsp;</p>

	</div>


	@if ($customGateway = $account->getGatewayByType(GATEWAY_TYPE_CUSTOM1))
		@include('invited.custom_gateway', ['customGateway' => $customGateway, 'number' => 1])
	@endif

	@if ($customGateway = $account->getGatewayByType(GATEWAY_TYPE_CUSTOM2))
		@include('invited.custom_gateway', ['customGateway' => $customGateway, 'number' => 2])
	@endif

	@if ($customGateway = $account->getGatewayByType(GATEWAY_TYPE_CUSTOM3))
		@include('invited.custom_gateway', ['customGateway' => $customGateway, 'number' => 3])
	@endif


	@if ($account->requiresAuthorization($invoice))
		<div class="modal fade" id="authorizationModal" tabindex="-1" role="dialog" aria-labelledby="authorizationModalLabel" aria-hidden="true">
		  <div class="modal-dialog">
			<div class="modal-content">
			  <div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title">{{ trans('texts.authorization') }}</h4>
			  </div>

			 <div class="panel-body">
				 @if ($invoice->terms)
					 <div class="well" style="max-height:300px;overflow-y:scroll">
						 {!! nl2br(e($invoice->terms)) !!}
					 </div>
				 @endif
				 @if ($account->showSignature($invoice))
				 	<div>
						{{ trans('texts.sign_here') }}
					</div>
				 	<div id="signature"></div><br/>
				 @endif
			  </div>

			  <div class="modal-footer">
				 @if ($account->showAcceptTerms($invoice))
 					<div class="pull-left">
 						<label for="termsCheckbox" style="font-weight:normal">
 							<input id="termsCheckbox" type="checkbox" onclick="setModalPayNowEnabled()"/>
 							&nbsp;{{ trans('texts.i_agree') }}
 						</label>
 					</div>
 				 @endif
				<button id="modalPayNowButton" type="button" class="btn btn-success" onclick="onModalPayNowClick()" disabled="">
					{{ $invoice->isQuote() ? trans('texts.approve') : trans('texts.pay_now') }}
				</button>
			  </div>
			</div>
		  </div>
		</div>
	@endif

@stop
