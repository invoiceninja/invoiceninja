Hello, here is the output of your recent import job.

If your logo imported correctly it will available display below. If it didn't import, you'll need to reupload your logo

{!! $company->present()->logo() !!}

@if(isset($company) && $company->clients->count() >=1)
    {!! ctrans('texts.clients') !!}: {!! $company->clients->count() !!} 
@endif

@if(isset($company) && count($company->products) >=1)
    {!! ctrans('texts.products') !!}: {!! count($company->products) !!} 
@endif

@if(isset($company) && count($company->invoices) >=1)
    {!! ctrans('texts.invoices') !!}: {!! count($company->invoices) !!} 
@endif

@if(isset($company) && count($company->payments) >=1)
    {!! ctrans('texts.payments') !!}: {!! count($company->payments) !!} 
@endif

@if(isset($company) && count($company->recurring_invoices) >=1)
    {!! ctrans('texts.recurring_invoices') !!}: {!! count($company->recurring_invoices) !!} 
@endif

@if(isset($company) && count($company->quotes) >=1)
    {!! ctrans('texts.quotes') !!}: {!! count($company->quotes) !!} 
@endif

@if(isset($company) && count($company->credits) >=1)
    {!! ctrans('texts.credits') !!}: {!! count($company->credits) !!} 
@endif

@if(isset($company) && count($company->projects) >=1)
    {!! ctrans('texts.projects') !!}: {!! count($company->projects) !!} 
@endif

@if(isset($company) && count($company->tasks) >=1)
    {!! ctrans('texts.tasks') !!}: {!! count($company->tasks) !!} 
@endif

@if(isset($company) && count($company->vendors) >=1)
    {!! ctrans('texts.vendors') !!}: {!! count($company->vendors) !!} 
@endif

@if(isset($company) && count($company->expenses) >=1)
    {!! ctrans('texts.expenses') !!}: {!! count($company->expenses) !!} 
@endif

@if(isset($company) && count($company->company_gateways) >=1)
    {!! ctrans('texts.gateways') !!}: {!! count($company->company_gateways) !!} 
@endif

@if(isset($company) && count($company->client_gateway_tokens) >=1)
    {!! ctrans('texts.tokens') !!}: {!! count($company->client_gateway_tokens) !!} 
@endif

@if(isset($company) && count($company->tax_rates) >=1)
    {!! ctrans('texts.tax_rates') !!}: {!! count($company->tax_rates) !!} 
@endif

@if(isset($company) && count($company->documents) >=1)
    {!! ctrans('texts.documents') !!}: {!! count($company->documents) !!} 
@endif

{!! $url !!}

{!! ctrans('texts.email_signature') !!}

{!! ctrans('texts.email_from') !!}


