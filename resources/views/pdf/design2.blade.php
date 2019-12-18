<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>$invoice_number</title>
    <link href="{{asset('/vendors/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{asset('/vendors/css/coreui.min.css') }}" rel="stylesheet">
    <style>

    html {
      -webkit-print-color-adjust: exact;
    }
    .invoice-box {
    }
    
    .invoice-box table {
        width: 100%;
        line-height: inherit;
        text-align: left;
    }
    
    .invoice-box table td {
        padding: 5px;
        vertical-align: top;
    }
    
    .invoice-box table tr td {
        text-align: center;
    }
    
    .invoice-box table tr.top table td {
        padding-bottom: 20px;
    }
    
    .invoice-box table tr.top table td.title {
        font-size: 45px;
        line-height: 45px;
        color: #333;
    }
    
    .invoice-box table tr.information table td {
        padding-bottom: 40px;
    }
    
    .invoice-box table tr.heading td {
        background: #eee;
        border-bottom: 1px solid #ddd;
        font-weight: bold;
    }
    
    .invoice-box table tr.details td {
        padding-bottom: 20px;
    }
    
    .invoice-box table tr.item td{
        border-bottom: 1px solid #eee;
    }
    
    .invoice-box table tr.item.last td {
        border-bottom: none;
    }
    
    .invoice-box table tr.total td:nth-child(2) {
        border-top: 0px solid #eee;
        font-weight: bold;
    }
    
    table.totals {
        width:50%;
        border-collapse: collapse;
        padding: 0;
    }



    table.totals tr td {
        width:0.1%;
        text-align: right;
        white-space: nowrap;  /** added **/
    }

    @media only screen and (max-width: 600px) {
        .invoice-box table tr.top table td {
            width: 100%;
            display: block;
            text-align: center;
        }
        
        .invoice-box table tr.information table td {
            width: 100%;
            display: block;
            text-align: center;
        }
    }
    
    /** RTL **/
    .rtl {
        direction: rtl;
        font-family: Tahoma, 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
    }
    
    .rtl table {
        text-align: right;
    }
    
    .rtl table tr td:nth-child(2) {
        text-align: left;
    }
    </style>
</head>

<body>
    <div class="container-fluid">
    <div class="invoice-box">
        <table>
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title">
                                <img src="$company.logo" style="width:100%; max-width:150px;">
                            </td>
                            
                            <td>
                                $invoice_number_label:  $number <br>
                                $invoice_date_label:  $date <br>
                                $invoice_due_date_label:  $due_date 
                                
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                $client_name<br>
                                $address1<br>
                                $address2<br>
                                $city_state_postal<br>
                                $country<br>
                                $vat_number<br>

                            </td>
                            
                            <td>
                                $company_name<br>
                                $company_address<br>
                                $phone<br>
                                $email<br>
                            </td>

                        </tr>
                    </table>
                </td>
            </tr>
    </table>
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

        <div class="container">
        <div class="row">
        <div class="pull-right">
            <table class="totals" border="1">         
                <tr class="subtotal">
                    <td>$subtotal_label:</td>
                    <td>$subtotal</td>
                </tr>
                <tr class="taxes">
                    <td>$taxes_label:</td>
                    <td>$taxes</td>
                </tr>

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
    </div>
    </div>
</body>
</html>