<div class="flex flex-col w-full">

<div>
    <dl>
        @foreach($data->pdf_variables->company_details as $cd)
        <dd>{{ $cd }}</dd>
        @endforeach
    </dl>
</div>
    
</div>