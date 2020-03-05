</div>
<footer class="app-footer">
    <div class="ml-auto">
        @if(!$user->company->account->isPaid())
            <span>Powered by</span>
            <a href="https://invoiceninja.com">InvoiceNinja</a>  &copy; 2019 Invoice Ninja LLC.
        @endif
    </div>
</footer>
<!-- Bootstrap and necessary plugins-->
<script src="/vendors/js/jquery.min.js"></script>
<script src="/vendors/js/bootstrap.bundle.min.js"></script>
<script src="/vendors/js/perfect-scrollbar.min.js"></script>
<script src="/vendors/js/coreui.min.js"></script>
@stack('scripts')
<script>
$('#ui-view').ajaxLoad();
</script>