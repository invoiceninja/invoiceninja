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
                        @include('partials/variables_help', ['entityType' => ENTITY_QUOTE, 'account' => auth()->user()->account])
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
