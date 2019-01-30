<div class="alert alert-info" id="whiteLabelExpired">
    {{ trans('texts.white_label_expired') }} &nbsp;&nbsp;
    <a href="#" onclick="buyWhiteLabel()" class="btn btn-primary btn-sm">{{ trans('texts.renew_license') }}</a>
    <a href="#" onclick="hideWhiteLabelExpiredMessage()" class="pull-right">{{ trans('texts.hide') }}</a>
</div>

<script type="text/javascript">
    function hideWhiteLabelExpiredMessage() {
        jQuery('#whiteLabelExpired').fadeOut();
        $.get('/white_label/hide_message', function(response) {
            console.log('Reponse: %s', response);
        });
        return false;
    }
</script>
