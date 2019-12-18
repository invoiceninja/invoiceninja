<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>$number</title>
    <link href="{{asset('/vendors/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{asset('/vendors/css/coreui.min.css') }}" rel="stylesheet">
    <style>

    html {
      -webkit-print-color-adjust: exact;
      font-size: 24px;
      font-family: Tahoma, 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;

    }

    .totals td {
        text-align: right;
    }

    table { 
        border-collapse: collapse; 
        border:0;
    }

    table.items thead{
        background-color:yellow!important;
        border-collapse: collapse; 
        font-weight:bold;
        overflow: auto;
        border:0;
           -webkit-print-color-adjust: exact; 

    }
    .items td {
        text-align: center;
    }

    table.items.heading {
        background-color:#000;
                border-collapse: collapse; 

    }

    .heading {

        background-color: rgb(255,0,0);;
        overflow: auto;
        text-align: left;
        color: #000;
    }

    </style>
</head>

<body>
    <div class="container">
        <div class="row mt-4">

                <div class="col-md-4">
                    <img src="$company.logo" style="width:100%; max-width:150px;">
                </div>

                <div class="col-md-4 ml-auto">
                $invoice_number_label:  $number <br>
                $date_label:  $date <br>
                $due_date_label:  $due_date 
                </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-4">
                <b>Client:</b><br>
                $client_name<br>
                $address1<br>
                $address2<br>
                $city_state_postal<br>
                $country<br>
                $vat_number<br>
            </div>

            <div class="col-md-4 ml-auto">
                $company.name<br>
                $company.address<br>
                $phone<br>
                $email<br>
            </div>

        </div>
                        
        {{-- 
            column variables:

                date
                discount
                product_key
                notes
                cost
                quantity
                tax_name1 
                tax_name2
                line_total
                custom_label1 ( will show as the following parameter as its value -> custom_invoice_value1 )
                custom_label2 ( will show as the following parameter as its value -> custom_invoice_value2 )
                custom_label3 ( will show as the following parameter as its value -> custom_invoice_value3 )
                custom_label4 ( will show as the following parameter as its value -> custom_invoice_value4 )
        --}}
        {!! $invoice->table(['product_key', 'notes', 'cost','quantity', 'discount', 'tax_name1', 'line_total']) !!}

        <div class="row">
            <div class="d-flex justify-content-end ml-auto mr-4">
                <table class="totals" border="0" cellpadding="5">         
                    <tr class="subtotal">
                        <td>$subtotal_label:</td>
                        <td>$subtotal</td>
                    </tr>

                    {{-- total_taxes html is populated server side, with a class of total_taxes,  you can customise your CSS here to override the defaults--}}

                    $total_taxes

                    {{-- line_taxes html is populated server side, with a class of line_items,  you can customise your CSS here to override the defaults--}}
                    
                    $line_taxes

                    <tr class="discount">
                        <td>$discount_label:</td>
                        <td>$discount</td>
                    </tr>
                    <tr class="total">
                        <td>$total_label:</td>
                        <td>$total</td>
                    </tr>

                    <tr class="balance">
                        <td>$balance_label:</td>
                        <td>$balance</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>