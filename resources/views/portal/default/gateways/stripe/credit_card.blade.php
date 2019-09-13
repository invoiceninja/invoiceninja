<!-- Stripe Credit Card TOKEN Form-->
@if($token)

<!-- Stripe Credit Card TOKEN Form-->

@else
<!-- Stripe Credit Card Payment Form-->
<div class="py-md-5">

</div>
<!-- Stripe Credit Card Payment Form-->
@endif


@push('scripts')
<script src="https://js.stripe.com/v3/"></script>
@endpush