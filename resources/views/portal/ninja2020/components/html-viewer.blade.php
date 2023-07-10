<div>
    <div id="company-details">
        @foreach($settings->pdf_variables->company_details as $variable)
        <p>{{ $variable }}</p>
        @endforeach
    </div>


    <div id="company-address">
        @foreach($settings->pdf_variables->company_address as $variable)
        <p>{{ $variable }}</p>
        @endforeach
    </div>

    <div id="entity-details"></div>

    <div id="user-details"></div>

    <div id="product-details"></div>
    
    <div id="task-details"></div>

    <div id="totals"></div>

    <div id="notes"></div>

    <div id="terms"></div>

    <div id="footer"></div>
</div>