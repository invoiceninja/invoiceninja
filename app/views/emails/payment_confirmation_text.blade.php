{{ $clientName }},

Thank you for your payment of {{ $paymentAmount }}.

@if ($emailFooter)
{{ $emailFooter }}
@else
Best regards,
{{ $accountName }}
@endif