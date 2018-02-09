{!! Button::normal(trans('texts.help'))
    ->appendIcon(Icon::create('question-sign'))
    ->withAttributes(['onclick' => 'showProposalHelp()']) !!}

<script>

function showProposalHelp() {
    $('#proposalHelpModal').modal('show');
}

</script>

<div class="modal fade" id="proposalHelpModal" tabindex="-1" role="dialog" aria-labelledby="proposalHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="text-align:left">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="proposalHelpModalLabel">{{ trans('texts.help') }}</h4>
            </div>

            <div class="container" style="width: 100%; padding-bottom: 0px !important">
                <div class="panel panel-default">
                    <div class="panel-body">

                        <div class="col-md-6">
                            <ul>
                                <li>$quote.quoteNumber</li>
                                <li>$quote.discount</li>
                                <li>$quote.poNumber</li>
                                <li>$quote.quoteDate</li>
                                <li>$quote.validUntil</li>
                                <li>$quote.publicNotes</li>
                                <li>$quote.amount</li>
                                <li>$quote.terms</li>
                                <li>$quote.footer</li>
                                <li>$quote.partial</li>
                                <li>$quote.partialDueDate</li>
                                <li>$quote.customValue1</li>
                                <li>$quote.customValue2</li>
                                <li>$quote.customTextValue1</li>
                                <li>$quote.customTextValue2</li>
                            </ul>
                            <ul>
                                <li>$contact.firstName</li>
                                <li>$contact.lastName</li>
                                <li>$contact.email</li>
                                <li>$contact.phone</li>
                                <li>$contact.customValue1</li>
                                <li>$contact.customValue2</li>
                            </ul>
                        </ul>
                        </div>
                        <div class="col-md-6">
                            <ul>
                                <li>$client.name</li>
                                <li>$client.idNumber</li>
                                <li>$client.vatNumber</li>
                                <li>$client.address1</li>
                                <li>$client.address2</li>
                                <li>$client.city</li>
                                <li>$client.state</li>
                                <li>$client.postalCode</li>
                                <li>$client.country.name</li>
                                <li>$client.phone</li>
                                <li>$client.balance</li>
                                <li>$client.customValue1</li>
                                <li>$client.customValue2</li>
                            </ul>
                            <ul>
                                <li>$account.name</li>
                                <li>$account.idNumber</li>
                                <li>$account.vatNumber</li>
                                <li>$account.address1</li>
                                <li>$account.address2</li>
                                <li>$account.city</li>
                                <li>$account.state</li>
                                <li>$account.postalCode</li>
                                <li>$account.country.name</li>
                                <li>$account.phone</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }}</button>
                <!-- <a class="btn btn-primary" href="{{ config('ninja.video_urls.custom_design') }}" target="_blank">{{ trans('texts.video') }}</a> -->
            </div>

        </div>
    </div>
</div>
