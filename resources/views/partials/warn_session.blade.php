<div class="container">
  <div class="alert alert-warning" id="keepAliveDiv" style="display:none">
    {!! trans('texts.page_expire', ['click_here' => link_to('#', trans('texts.click_here'), ['onclick' => 'keepAlive()'])]) !!}
  </div>
</div>

<script type="text/javascript">
    var redirectTimer = null;
    function startWarnSessionTimeout() {
        var oneMinute = 1000 * 60;
        var twoMinutes = oneMinute * 2;
        var twoHours = oneMinute * 120;
        setTimeout(function() {
            warnSessionExpring();
        }, (twoHours - twoMinutes));
    }

    function warnSessionExpring() {
        $("#keepAliveDiv").fadeIn();
        redirectTimer = setTimeout(function() {
            NINJA.formIsChanged = false;
            window.location = '{{ URL::to($redirectTo) }}';
        }, 1000 * 60);
    }

    // keep the token cookie valid to prevent token mismatch errors
    function keepAlive() {
        clearTimeout(redirectTimer);
        $('#keepAliveDiv').fadeOut();
        $.get('{{ URL::to('/keep_alive') }}');
        startWarnSessionTimeout();
    }

    $(function() {
        if ($('form.warn-on-exit, form.form-signin').length > 0) {
            startWarnSessionTimeout();
        }
    });
</script>