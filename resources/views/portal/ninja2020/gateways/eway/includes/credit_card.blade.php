<script type="text/javascript">


var labelStyles = "padding-right: 20px; float: right;";
var publicApiKey = "{{ $public_api_key }}";
var cardStyles = "padding: 2px; border: 1px solid #AAA; border-radius: 3px; height: 34px; width: 100%;";
var rowStyles = "";
var groupStyles = "";

var groupFieldConfig = {
        publicApiKey: publicApiKey,
        fieldDivId: "eway-secure-panel",
        fieldType: "group",
        styles: groupStyles,
        layout : {
            fonts: [ 
                "Lobster"
             ],
            rows : [ 
                {
                    styles: rowStyles,
                    cells: [
                        {
                            colSpan: 12,
                            styles: "margin-top: 15px;",
                            label: {
                                fieldColSpan: 4,
                                text: "Card Name:",
                                styles: "",
                            },
                            field: {
                                fieldColSpan: 8,
                                fieldType: "name",
                                styles: cardStyles,
                                divStyles: "padding-left: 10px;"
                            }
                        },
                        {
                            colSpan: 12,
                            styles: "margin-top: 15px;",
                            label: {
                                fieldColSpan: 4,
                                text: "Expiry:",
                                styles: "",
                            },
                            field: {
                                fieldColSpan: 8,
                                fieldType: "expirytext",
                                styles: cardStyles,
                                divStyles: "padding-left: 10px;"
                            }
                        }
                    ]
                },
                {
                    styles: rowStyles,
                    cells: [
                        {
                            colSpan: 12,
                            styles: "margin-top: 15px;",
                            label: {
                                fieldColSpan: 4,
                                text: "Card Number:",
                                styles: "",
                            },
                            field: {
                                fieldColSpan: 8,
                                fieldType: "card",
                                styles: cardStyles,
                            }
                        },
                        {
                            colSpan: 12,
                            styles: "margin-top: 15px;",
                            label: {
                                fieldColSpan: 4,
                                text: "CVV Number:",
                                styles: "",
                            },
                            field: {
                                fieldColSpan: 8,
                                fieldType: "cvn",
                                styles: cardStyles,
                            }
                        }
                    ]
                }
            ]
        }
    };

function securePanelCallback(event) {
    if (!event.fieldValid) {
        alert(getError(event.errors));
    } else {
        var s = document.querySelector("input[name=securefieldcode]");
        s.value = event.secureFieldCode
        console.log(s.value);
    }
}

function doneCallback() {
    console.log("done call bak");
    var form = document.getElementById("server-response");
    form.submit();
}

function saveAndSubmit() {
    console.log("save and sub");
    eWAY.saveAllFields(doneCallback, 2000); 
    return false;
}


function getError(k){
    myArr = k.split(" ");

    var str = "";
    
    for(error in myArr){
        str = str.concat(map.get(myArr[error])) + '\n';
    }

    return str;
}

const map = new Map();
map.set('V6000',  'Validation error');
map.set('V6001',  'Invalid CustomerIP');
map.set('V6002',  'Invalid DeviceID');
map.set('V6003',  'Invalid Request PartnerID');
map.set('V6004',  'Invalid Request Method');
map.set('V6010',  'Invalid TransactionType, account not certified for eCome only MOTO or Recurring available');
map.set('V6011',   'Invalid Payment TotalAmount');
map.set('V6012',   'Invalid Payment InvoiceDescription');
map.set('V6013',   'Invalid Payment InvoiceNumber');
map.set('V6014',   'Invalid Payment InvoiceReference');
map.set('V6015',   'Invalid Payment CurrencyCode');
map.set('V6016',   'Payment Required');
map.set('V6017',   'Payment CurrencyCode Required');
map.set('V6018',   'Unknown Payment CurrencyCode');
map.set('V6019',   'Cardholder identity authentication required');
map.set('V6020',   'Cardholder Input Required');
map.set('V6021',   'EWAY_CARDHOLDERNAME Required');
map.set('V6022',   'EWAY_CARDNUMBER Required');
map.set('V6023',   'EWAY_CARDCVN Required');
map.set('V6024',   'Cardholder Identity Authentication One Time Password Not Active Yet');
map.set('V6025',   'PIN Required');
map.set('V6033',   'Invalid Expiry Date');
map.set('V6034',   'Invalid Issue Number');
map.set('V6035',   'Invalid Valid From Date');
map.set('V6039',   'Invalid Network Token Status');
map.set('V6040',   'Invalid TokenCustomerID');
map.set('V6041',  'Customer Required');
map.set('V6042',  'Customer FirstName Required');
map.set('V6043',  'Customer LastName Required');
map.set('V6044',  'Customer CountryCode Required');
map.set('V6045',  'Customer Title Required');
map.set('V6046',  'TokenCustomerID Required');
map.set('V6047',  'RedirectURL Required');
map.set('V6048',  'CheckoutURL Required when CheckoutPayment specified');
map.set('V6049',  'nvalid Checkout URL');
map.set('V6051',  'Invalid Customer FirstName');
map.set('V6052',  'Invalid Customer LastName');
map.set('V6053',  'Invalid Customer CountryCode');
map.set('V6058',  'Invalid Customer Title');
map.set('V6059',  'Invalid RedirectURL');
map.set('V6060',  'Invalid TokenCustomerID');
map.set('V6061',  'Invalid Customer Reference');
map.set('V6062',  'Invalid Customer CompanyName');
map.set('V6063',  'Invalid Customer JobDescription');
map.set('V6064',  'Invalid Customer Street1');
map.set('V6065',  'Invalid Customer Street2');
map.set('V6066',  'Invalid Customer City');
map.set('V6067',  'Invalid Customer State');
map.set('V6068',  'Invalid Customer PostalCode');
map.set('V6069',  'Invalid Customer Email');
map.set('V6070',  'Invalid Customer Phone');
map.set('V6071',  'Invalid Customer Mobile');
map.set('V6072',  'Invalid Customer Comments');
map.set('V6073',  'Invalid Customer Fax');
map.set('V6074',  'Invalid Customer URL');
map.set('V6075',  'Invalid ShippingAddress FirstName');
map.set('V6076',  'Invalid ShippingAddress LastName');
map.set('V6077',  'Invalid ShippingAddress Street1');
map.set('V6078',  'Invalid ShippingAddress Street2');
map.set('V6079',  'Invalid ShippingAddress City');
map.set('V6080',  'Invalid ShippingAddress State');
map.set('V6081',  'Invalid ShippingAddress PostalCode');
map.set('V6082',  'Invalid ShippingAddress Email');
map.set('V6083',  'Invalid ShippingAddress Phone');
map.set('V6084',  'Invalid ShippingAddress Country');
map.set('V6085',  'Invalid ShippingAddress ShippingMethod');
map.set('V6086',  'Invalid ShippingAddress Fax');
map.set('V6091',  'Unknown Customer CountryCode');
map.set('V6092',  'Unknown ShippingAddress CountryCode');
map.set('V6093',  'Insufficient Address Information');
map.set('V6100',  'Invalid EWAY_CARDNAME');
map.set('V6101',  'Invalid EWAY_CARDEXPIRYMONTH');
map.set('V6102',  'Invalid EWAY_CARDEXPIRYYEAR');
map.set('V6103',  'Invalid EWAY_CARDSTARTMONTH');
map.set('V6104',  'Invalid EWAY_CARDSTARTYEAR');
map.set('V6105',  'Invalid EWAY_CARDISSUENUMBER');
map.set('V6106',  'Invalid EWAY_CARDCVN');
map.set('V6107',  'Invalid EWAY_ACCESSCODE');
map.set('V6108',  'Invalid CustomerHostAddress');
map.set('V6109',  'Invalid UserAgent');
map.set('V6110',  'Invalid EWAY_CARDNUMBER');
map.set('V6111',  'Unauthorised API Access, Account Not PCI Certified');
map.set('V6112',  'Redundant card details other than expiry year and month');
map.set('V6113',  'Invalid transaction for refund');
map.set('V6114',  'Gateway validation error');
map.set('V6115',  'Invalid DirectRefundRequest, Transaction ID');
map.set('V6116',  'Invalid card data on original TransactionID');
map.set('V6117',  'Invalid CreateAccessCodeSharedRequest, FooterText');
map.set('V6118',  'Invalid CreateAccessCodeSharedRequest, HeaderText');
map.set('V6119',  'Invalid CreateAccessCodeSharedRequest, Language');
map.set('V6120',  'Invalid CreateAccessCodeSharedRequest, LogoUrl');
map.set('V6121',  'Invalid TransactionSearch, Filter Match Type');
map.set('V6122',  'Invalid TransactionSearch, Non numeric Transaction ID');
map.set('V6123',  'Invalid TransactionSearch,no TransactionID or AccessCode specified');
map.set('V6124',  'Invalid Line Items. The line items have been provided however the totals do not match the TotalAmount field');
map.set('V6125',  'Selected Payment Type not enabled');
map.set('V6126',  'Invalid encrypted card number, decryption failed');
map.set('V6127',  'Invalid encrypted cvn, decryption failed');
map.set('V6128',  'Invalid Method for Payment Type');
map.set('V6129',  'Transaction has not been authorised for Capture/Cancellation');
map.set('V6130',  'Generic customer information error');
map.set('V6131',  'Generic shipping information error');
map.set('V6132',  'Transaction has already been completed or voided, operation not permitted');
map.set('V6133',  'Checkout not available for Payment Type');
map.set('V6134',  'Invalid Auth Transaction ID for Capture/Void');
map.set('V6135',  'PayPal Error Processing Refund');
map.set('V6136',  'Original transaction does not exist or state is incorrect');
map.set('V6140',  'Merchant account is suspended');
map.set('V6141',  'Invalid PayPal account details or API signature');
map.set('V6142',  'Authorise not available for Bank/Branch');
map.set('V6143',  'Invalid Public Key');
map.set('V6144',  'Method not available with Public API Key Authentication');
map.set('V6145',  'Credit Card not allow if Token Customer ID is provided with Public API Key Authentication');
map.set('V6146',  'Client Side Encryption Key Missing or Invalid');
map.set('V6147',  'Unable to Create One Time Code for Secure Field');
map.set('V6148',  'Secure Field has Expired');
map.set('V6149',  'Invalid Secure Field One Time Code');
map.set('V6150',  'Invalid Refund Amount');
map.set('V6151',  'Refund amount greater than original transaction');
map.set('V6152',  'Original transaction already refunded for total amount');
map.set('V6153',  'Card type not support by merchant');
map.set('V6154',  'Insufficent Funds Available For Refund');
map.set('V6155',  'Missing one or more fields in request');
map.set('V6160',  'Encryption Method Not Supported');
map.set('V6161',  'Encryption failed, missing or invalid key');
map.set('V6165',  'Invalid Click-to-Pay (Visa Checkout) data or decryption failed');
map.set('V6170',  'Invalid TransactionSearch, Invoice Number is not unique');
map.set('V6171',  'Invalid TransactionSearch, Invoice Number not found');
map.set('V6220',  'Three domain secure XID invalid');
map.set('V6221',  'Three domain secure ECI invalid');
map.set('V6222',  'Three domain secure AVV invalid');
map.set('V6223',  'Three domain secure XID is required');
map.set('V6224',  'Three Domain Secure ECI is required');
map.set('V6225',  'Three Domain Secure AVV is required');
map.set('V6226',  'Three Domain Secure AuthStatus is required');
map.set('V6227',  'Three Domain Secure AuthStatus invalid');
map.set('V6228',  'Three domain secure Version is required');
map.set('V6230',  'Three domain secure Directory Server Txn ID invalid');
map.set('V6231',  'Three domain secure Directory Server Txn ID is required');
map.set('V6232',  'Three domain secure Version is invalid');
map.set('V6501',  'Invalid Amex InstallementPlan');
map.set('V6502',  'Invalid Number Of Installements for Amex. Valid values are from 0 to 99 inclusive');
map.set('V6503',  'Merchant Amex ID required');
map.set('V6504',  'Invalid Merchant Amex ID');
map.set('V6505',  'Merchant Terminal ID required');
map.set('V6506',  'Merchant category code required');
map.set('V6507',  'Invalid merchant category code');
map.set('V6508',  'Amex 3D ECI required');
map.set('V6509',  'Invalid Amex 3D ECI');
map.set('V6510',  'Invalid Amex 3D verification value');
map.set('V6511',  'Invalid merchant location data');
map.set('V6512',  'Invalid merchant street address');
map.set('V6513',  'Invalid merchant city');
map.set('V6514',  'Invalid merchant country');
map.set('V6515',  'Invalid merchant phone');
map.set('V6516',  'Invalid merchant postcode');
map.set('V6517',  'Amex connection error');
map.set('V6518',  'Amex EC Card Details API returned invalid data');
map.set('V6520',  'Invalid or missing Amex Point Of Sale Data');
map.set('V6521',  'Invalid or missing Amex transaction date time');
map.set('V6522',  'Invalid or missing Amex Original transaction date time');
map.set('V6530',  'Credit Card Number in non Credit Card Field');


</script>