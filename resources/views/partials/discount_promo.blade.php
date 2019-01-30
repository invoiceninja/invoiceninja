<div class="alert alert-info" id="discountPromo">
    {{ $account->company->present()->promoMessage }} &nbsp;&nbsp;
    <a href="#" onclick="showUpgradeModal()" class="btn btn-primary btn-sm">{{ trans('texts.plan_upgrade') }}</a>
    <a href="#" onclick="hideDiscountMessage()" class="pull-right">{{ trans('texts.hide') }}</a>
</div>

<script type="text/javascript">

    function hideDiscountMessage() {
        jQuery('#discountPromo').fadeOut();
        return false;
    }

</script>
