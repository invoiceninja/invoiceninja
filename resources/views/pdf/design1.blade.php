<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>A simple, clean, and responsive HTML invoice template</title>
    
    <style>

        html {
  -webkit-print-color-adjust: exact;
}
    .invoice-box {
        max-width: 800px;
        margin: auto;
        padding: 30px;
        border: 1px solid #eee;
        box-shadow: 0 0 10px rgba(0, 0, 0, .15);
        font-size: 16px;
        line-height: 24px;
        font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
        color: #555;
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
    
    .invoice-box table tr td:nth-child(2) {
        text-align: right;
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
        border-top: 2px solid #eee;
        font-weight: bold;
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
    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title">
                                <img src="https://www.sparksuite.com/images/logo.png" style="width:100%; max-width:300px;">
                            </td>
                            
                            <td>
                                {{$invoice_number_label}}: {{ $invoice->invoice_number }}<br>
                                {{$invoice_date_label}}: {{ $invoice->invoice_date }}<br>
                                {{$invoice_due_date_label}}: {{ $invoice->due_date }}
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
                                {{$client_name}}<br>
                                {{$address1}}<br>
                                {{$address2}}<br>
                                {{$city_state_postal}}<br>
                                {{$country}}<br>
                                {{$vat_number}}<br>

                            </td>
                            
                            <td>
                                {{$company_name}}<br>
                                {{$phone}}<br>
                                {{$email}}<br>
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
        {!! $invoice->table(['product_key', 'notes', 'cost','quantity', 'line_total']) !!}

   
        <table>         
            <tr class="total">
                <td></td>
                
                <td>
                   Total: $385.00
                </td>
            </tr>
        </table>
    </div>
</body>
</html>