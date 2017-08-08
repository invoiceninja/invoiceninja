<div class="alert alert-info" id="bluevinePromo">
    {{ trans('texts.bluevine_promo') }} &nbsp;&nbsp;
    <a href="#" onclick="showBlueVineModal()"
       class="btn btn-primary btn-sm">{{ trans('texts.learn_more') }}</a>
    <a href="#" onclick="hideBlueVineMessage()" class="pull-right">{{ trans('texts.hide') }}</a>
</div>
<div class="modal fade" id="bluevineModal" tabindex="-1" role="dialog" aria-labelledby="bluevineModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"
                    id="bluevineModalLabel">{{ trans('texts.bluevine_modal_label') }}</h4>
            </div>

            <div class="container" style="width: 100%; padding-bottom: 0px !important">
            <div class="panel panel-default">
            <div class="panel-body">
                {!! Former::open('/bluevine/signup')->id('bluevineSignup') !!}
                {!! trans('texts.bluevine_modal_text') !!}<br/>
                <h3>{!! trans('texts.bluevine_create_account') !!}</h3>
                {!! Former::text('name')->id('bluevine_name')->placeholder(trans('texts.name'))->value($user->first_name . ' ' . $user->last_name)->required() !!}
                {!! Former::text('email')->id('bluevine_email')->placeholder(trans('texts.email'))->value($user->email)->required() !!}
                {!! Former::text('phone')->id('bluevine_phone')->placeholder(trans('texts.phone'))->value(!empty($user->phone) ? $user->phone : '')->maxlength(10)->required() !!}
                {!! Former::number('fico_score')->min(300)->max(850)->placeholder(trans('texts.fico_score'))->required() !!}
                {!! Former::text('business_inception')->append('<span class="glyphicon glyphicon-calendar"></span>')->placeholder(trans('texts.business_inception'))->required() !!}
                {!! Former::number('annual_revenue')->prepend('$')->append('.00')->placeholder(trans('texts.annual_revenue'))->value(floor($usdLast12Months))->required() !!}
                {!! Former::number('average_bank_balance')->prepend('$')->append('.00')->placeholder(trans('texts.average_bank_balance'))->required() !!}
                {!! Former::checkboxes('quote_types')
                        ->onchange('bluevineQuoteTypesChanged()')
                        ->required()
                        ->checkboxes([
                            trans('texts.invoice_factoring') => ['value' => 'invoice_factoring', 'name' => 'quote_type_factoring', 'id'=>'quote_type_factoring'],
                            trans('texts.line_of_credit') => ['value' => 'line_of_credit', 'name' => 'quote_type_loc', 'id'=>'quote_type_loc'],
                        ]) !!}
                {!! Former::number('desired_credit_limit_factoring')
                    ->id('desired_credit_limit_factoring')
                    ->name('desired_credit_limit[invoice_factoring]')
                    ->prepend('$')->append('.00')
                    ->value(5000)
                    ->required()
                    ->placeholder(trans('texts.desired_credit_limit'))
                    ->label(trans('texts.desired_credit_limit_factoring'))!!}
                {!! Former::number('desired_credit_limit_loc')
                    ->id('desired_credit_limit_loc')
                    ->name('desired_credit_limit[line_of_credit]')
                    ->prepend('$')->append('.00')
                    ->value(5000)
                    ->required()
                    ->placeholder(trans('texts.desired_credit_limit'))
                    ->label(trans('texts.desired_credit_limit_loc'))!!}
                {!! Former::close() !!}
            </div>
            </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                        data-dismiss="modal">{{ trans('texts.cancel') }}</button>
                <button type="button" class="btn btn-primary"
                        onclick="bluevineCreateAccount()">{{ trans('texts.sign_up') }}</button>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    function hideBlueVineMessage() {
        jQuery('#bluevinePromo').fadeOut();
        $.get('/bluevine/hide_message', function(response) {
            console.log('Reponse: %s', response);
        });
        return false;
    }

    function showBlueVineModal() {
        jQuery('#bluevineModal').modal('show');
        return false;
    }

    function bluevineQuoteTypesChanged() {
        if (jQuery('#quote_type_loc').is(':checked')) {
            jQuery('#desired_credit_limit_loc').attr('required', 'required').closest('.form-group').show();
        } else {
            jQuery('#desired_credit_limit_loc').removeAttr('required').closest('.form-group').hide();
        }

        if (jQuery('#quote_type_factoring').is(':checked')) {
            jQuery('#desired_credit_limit_factoring').attr('required', 'required').closest('.form-group').show();
        } else {
            jQuery('#desired_credit_limit_factoring').removeAttr('required').closest('.form-group').hide();
        }
    }
    bluevineQuoteTypesChanged();

    jQuery('#bluevineSignup').on('submit', function (e) {
        e.preventDefault();
        bluevineCreateAccount();
    });

    jQuery('#business_inception').datepicker().siblings('.input-group-addon').click(function () {
        jQuery('#business_inception').focus();
    });

    function bluevineCreateAccount() {
        var form = $('#bluevineSignup');
        $('#bluevineModal').find('.alert').remove();

        var fields = [
            'bluevine_name',
            'bluevine_email',
            'bluevine_phone',
            'fico_score',
            'business_inception',
            'annual_revenue',
            'average_bank_balance'
        ];

        var hasError = false;

        var requestFactoring = jQuery('#quote_type_factoring').is(':checked');
        var requestLoc = jQuery('#quote_type_loc').is(':checked');

        var quoteTypeFormGroup = jQuery('#quote_type_factoring').closest('.form-group');
        if (!requestFactoring && !requestLoc) {
            hasError = true;
            if (!quoteTypeFormGroup.hasClass('has-error')) {
                quoteTypeFormGroup.addClass('has-error');
                quoteTypeFormGroup.children('div').append(
                        jQuery('<div class="help-block error-help-block">').text("{{ trans('texts.bluevine_credit_line_type_required') }}")
                );
            }
        } else {
            quoteTypeFormGroup.removeClass('has-error').find('.error-help-block').remove();
        }

        if (requestFactoring) {
            fields.push('desired_credit_limit_factoring')
        }

        if (requestLoc) {
            fields.push('desired_credit_limit_loc')
        }

        $.each(fields, function (i, fieldId) {
            var field = $('#' + fieldId);
            var formGroup = field.closest('.form-group');
            if (!field.val()) {
                if (!formGroup.hasClass('has-error')) {
                    formGroup.addClass('has-error');
                    formGroup.children('div').append(
                            jQuery('<div class="help-block error-help-block">').text("{{ trans('texts.bluevine_field_required') }}")
                    );
                }
                hasError = true;
            } else {
                formGroup.removeClass('has-error').find('.error-help-block').remove();
            }
        });

        if (hasError) {
            return;
        }

        $('#bluevineModal .btn-primary').attr('disabled', 'disabled');
        $.post(form.attr('action'), form.serialize(), function (data) {
            if (!data.error) {
                $('#bluevineSignup').hide();
                var factoringOffer, locOffer;

                if (data.factoring_offer)factoringOffer = data.factoring_offer;
                else if (data.invoice_factoring_offer)factoringOffer = data;

                if (data.loc_offer)locOffer = data.loc_offer;
                else if (data.line_of_credit_offer)locOffer = data;

                var hasOffer, redirectUrl;

                if (!hasOffer && factoringOffer) {
                    hasOffer = factoringOffer.is_conditional_offer;
                    redirectUrl = factoringOffer.external_register_url;
                }

                if (!hasOffer && locOffer) {
                    hasOffer = locOffer.is_conditional_offer;
                    redirectUrl = locOffer.external_register_url;
                }

                if (!hasOffer) {
                    window.location.href = redirectUrl;
                } else {
                    if (factoringOffer) {
                        var quoteDetails = jQuery('<div class="bluevine-quote">');
                        if (factoringOffer.is_conditional_offer) {
                            quoteDetails.append(jQuery('<h4>').text("{{ trans('texts.bluevine_conditional_offer') }}"));

                            quoteDetails.append(jQuery('<div class="row">').append(
                                    jQuery('<strong class="col-sm-3">').text("{{ trans('texts.bluevine_credit_line_amount') }}"),
                                    jQuery('<div class="col-sm-2">').text(('$' + factoringOffer.credit_line_amount).replace(/(\d)(?=(\d{3})+$)/g, '$1,'))// Add commas to number
                            ));

                            // Docs claim that advance_rate is a percent from 0 to 100 without fraction,
                            // but in my testing the number was a percent from 0 to 1.
                            var advanceRate = factoringOffer.advance_rate > 1 ? factoringOffer.advance_rate : factoringOffer.advance_rate * 100;
                            quoteDetails.append(jQuery('<div class="row">').append(
                                    jQuery('<strong class="col-sm-3">').text("{{ trans('texts.bluevine_advance_rate') }}"),
                                    jQuery('<div class="col-sm-2">').text(advanceRate + '%')
                            ));

                            quoteDetails.append(jQuery('<div class="row">').append(
                                    jQuery('<strong class="col-sm-3">').text("{{ trans('texts.bluevine_weekly_discount_rate') }}"),
                                    jQuery('<div class="col-sm-2">').text(factoringOffer.weekly_discount_rate + '%')
                            ));

                            quoteDetails.append(jQuery('<div class="row">').append(
                                    jQuery('<strong class="col-sm-3">').text("{{ trans('texts.bluevine_minimum_fee_rate') }}"),
                                    jQuery('<div class="col-sm-2">').text(factoringOffer.minimum_fee_rate + '%')
                            ));
                        } else {
                            quoteDetails.append(jQuery('<p>').text("{{trans('texts.bluevine_no_conditional_offer')}}"));
                        }

                        $('#bluevineModal .panel-body').append(
                                jQuery('<h3>').text("{{ trans('texts.bluevine_invoice_factoring') }}"),
                                quoteDetails
                        );
                    }

                    if (locOffer) {
                        var quoteDetails = jQuery('<div class="bluevine-quote">');
                        if (locOffer.is_conditional_offer) {
                            quoteDetails.append(jQuery('<h4>').text("{{ trans('texts.bluevine_conditional_offer') }}"));

                            quoteDetails.append(jQuery('<div class="row">').append(
                                    jQuery('<strong class="col-sm-3">').text("{{ trans('texts.bluevine_credit_line_amount') }}"),
                                    jQuery('<div class="col-sm-2">').text(('$' + locOffer.credit_line_amount).replace(/(\d)(?=(\d{3})+$)/g, '$1,'))// Add commas to number
                            ));

                            quoteDetails.append(jQuery('<div class="row">').append(
                                    jQuery('<strong class="col-sm-3">').text("{{ trans('texts.bluevine_interest_rate') }}"),
                                    jQuery('<div class="col-sm-2">').text(locOffer.interest_rate + '%')
                            ));

                            quoteDetails.append(jQuery('<div class="row">').append(
                                    jQuery('<strong class="col-sm-3">').text("{{ trans('texts.bluevine_weekly_draw_rate') }}"),
                                    jQuery('<div class="col-sm-2">').text(locOffer.weekly_draw_rate + '%')
                            ));
                        } else {
                            quoteDetails.append(jQuery('<p>').text("{{trans('texts.bluevine_no_conditional_offer')}}"));
                        }

                        $('#bluevineModal .panel-body').append(
                                jQuery('<h3>').text("{{ trans('texts.bluevine_line_of_credit') }}"),
                                quoteDetails
                        );
                    }
                    /*<div class="row"><strong class="col-sm-4">Credit Line Amount</strong>  <div class="col-sm-2">$60,000</div>
                     </div>
                     <div class="row">
                     <strong class="col-sm-4">Advance Rate</strong>  <div class="col-sm-4">90%</div>
                     </div>
                     <div class="row">
                     <strong class="col-sm-4">Weekly Discount Rate</strong>  <div class="col-sm-2">0.8%</div>
                     </div>

                     <div class="row">
                     <strong class="col-sm-4">Minimum Rate</strong>  <div class="col-sm-2">1.5%</div>
                     </div>*/
                }

                $('#bluevineModal .btn-primary').replaceWith(
                        jQuery('<a class="btn btn-primary">').attr('href', redirectUrl).text("{{ trans('texts.bluevine_continue') }}")
                )
            } else {
                $('#bluevineModal .panel-body').append(
                        jQuery('<div class="alert alert-danger">').text(data.message ? data.message : "{{ trans('texts.bluevine_unexpected_error') }}")
                );
            }

            $('#bluevineModal .btn-primary').removeAttr('disabled');
        }, 'json').error(
                function () {
                    $('#bluevineModal .panel-body').append(
                            jQuery('<div class="alert alert-danger">').text("{{ trans('texts.bluevine_unexpected_error') }}")
                    );
                    $('#bluevineModal .btn-primary').removeAttr('disabled');
                }
        );
    }
</script>
