<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration\Einvoice;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use Tests\MockAccountData;
use Illuminate\Support\Str;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Repositories\InvoiceRepository;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ZugferdTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    // 1. surcharges inclusive taxes and discounts amount total amount and line items
    // 2. surcharges inclusive taxes and discounts amount total percent and line items
    // 3. surcharges inclusive taxes and discounts amount total percent
    // 4. surcharges inclusive taxes and discounts amount total
    // 5. surcharges inclusive taxes
    // 6. surcharges
    // 7. Exclusive TAXES +DISC+amount+linedisc
    // 8. Exclusive TAXES +DISC+perc+linedisc
    // 9. INCLUSIVE TAXES +DISC+amount+linedisc
    // 10. INCLUSIVE TAXES +DISC+per+linedisc
    // 11. INCLUSIVE TAXES +DISC+amount
    // 12. INCLUSIVE TAXES +DISC
    // 13. INCLUSIVE TAXES
    // 14. TOTAL DISCOUNT
    // 15. NO DISCOUNTS

    private array $inclusive_scenarios = [
        '{"status_id":1,"number":"V2025-0419","discount":"11.000000","is_amount_discount":true,"po_number":"surcharges inclusive taxes and discounts amount total amount and line items","date":"2025-01-20","last_sent_date":null,"due_date":null,"is_deleted":false,"line_items":[{"_id":"d5be23a5-2d2a-4f86-af38-778dfca3e819","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":10,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1117.58,"tax_amount":178.29,"gross_line_total":1117.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":312.79,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"d48502a3-0929-40e0-bfa2-062b4240ee61","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"33","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":92.78,"gross_line_total":581.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":162.77,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"92378d04-f9e9-4f7c-9041-927d7d6b9b12","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":359.79,"gross_line_total":2255.28,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":631.21,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"4c2c85cc-4281-4e3b-8142-234b2e8a89fd","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":126.02,"gross_line_total":789.96,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":221.1,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"e9e31815-7c1b-4e3e-9107-0e639840abf2","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1276.97,"gross_line_total":8004.4,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":672.09,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"7c64d513-d525-45be-8608-b00dc841bc1d","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":107.9,"gross_line_total":676.35,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":189.3,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"uses_inclusive_taxes":1,"custom_surcharge1":"123.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"13537.150000","balance":"0.000000","partial":"0.000000","partial_due_date":null,"last_viewed":null}',
        '{"assigned_user_id":null,"status_id":2,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0418","discount":"10.000000","is_amount_discount":false,"po_number":"surcharges inclusive taxes and discounts amount total percent and line items","date":"2025-01-20","last_sent_date":null,"due_date":"2025-02-19","is_deleted":false,"line_items":[{"_id":"acef1cc3-96da-47db-ba03-4f08ab10d0eb","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":10,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1014.82,"tax_amount":"145.83","gross_line_total":1014.82,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":255.84,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"122eab54-d048-4afb-94bd-0ad5f08589e3","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":"83.57","gross_line_total":581.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":146.62,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"b38cebe8-3ca2-4d51-921a-90cadd2befe6","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":"324.08","gross_line_total":2255.28,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":568.56,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"14699f8b-87b1-41e0-9812-bf7386b50268","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":"113.52","gross_line_total":789.96,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":199.15,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"4ed0b507-e31a-4b90-a269-9d3021a29630","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":"1150.21","gross_line_total":8004.4,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":605.38,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"42083848-bb2c-4789-b8b6-515e4bea5b45","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":"97.19","gross_line_total":676.35,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":170.51,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":"<p><strong>Footer:<\/strong> If you have any questions regarding this invoice, please contact us at [email\/phone number]. Thank you for your business! All payments should be made to [Company Name] via [Payment Methods, e.g., bank transfer, credit card, etc.]. Our banking details are listed above for your convenience.<\/p>","public_notes":null,"terms":"<p><strong>Terms:<\/strong> Payment is due within 30 days from the invoice date unless otherwise agreed in writing. Late payments may incur interest at a rate of [specify rate, e.g., 1.5% per month] from the due date. Please include the invoice number when making payments to ensure proper allocation.<\/p>","tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"1934.040000","uses_inclusive_taxes":1,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":"2025-01-21 01:00:00","custom_surcharge1":"123.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"12113.150000","balance":"12113.150000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737373562,"updated_at":1737415557,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"YQdJWA9eOG","status":-2}',
        '{"assigned_user_id":null,"status_id":1,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0417","discount":"10.000000","is_amount_discount":false,"po_number":"surcharges inclusive taxes and discounts amount total percent","date":"2025-01-20","last_sent_date":null,"due_date":null,"is_deleted":false,"line_items":[{"_id":"cc269f6f-3974-41bb-ab12-c3fb5e837a1c","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1127.58,"tax_amount":"162.03","gross_line_total":1127.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":284.26,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"6e81fcf0-2e11-4015-bce7-1ed5cb859512","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":"83.57","gross_line_total":581.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":146.62,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"be1a65c1-a42d-46a7-b988-2735adc71f58","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":"324.08","gross_line_total":2255.28,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":568.56,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"a09f1edb-f581-4fd2-86d0-572c2aef6f07","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":"113.52","gross_line_total":789.96,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":199.15,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"0c4529cb-e91c-4c33-b742-2014c0b72e0b","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":"1150.21","gross_line_total":8004.4,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":605.38,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"65a1fd4f-682a-4d38-82b9-69687949cd89","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":"97.19","gross_line_total":676.35,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":170.51,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":"<p><strong>Footer:<\/strong> If you have any questions regarding this invoice, please contact us at [email\/phone number]. Thank you for your business! All payments should be made to [Company Name] via [Payment Methods, e.g., bank transfer, credit card, etc.]. Our banking details are listed above for your convenience.<\/p>","public_notes":null,"terms":"<p><strong>Terms:<\/strong> Payment is due within 30 days from the invoice date unless otherwise agreed in writing. Late payments may incur interest at a rate of [specify rate, e.g., 1.5% per month] from the due date. Please include the invoice number when making payments to ensure proper allocation.<\/p>","tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"1950.240000","uses_inclusive_taxes":1,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":null,"custom_surcharge1":"123.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"12214.630000","balance":"0.000000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737373538,"updated_at":1737373538,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"YRdGWxrdDz","status":1}',
        '{"assigned_user_id":null,"status_id":2,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0416","discount":"10.000000","is_amount_discount":true,"po_number":"surcharges inclusive taxes and discounts amount total","date":"2025-01-20","last_sent_date":null,"due_date":"2025-02-19","is_deleted":false,"line_items":[{"_id":"815ea432-eadc-41ce-aa5f-42060cd2adc6","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1127.58,"tax_amount":179.9,"gross_line_total":1127.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":315.61,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"4b895f38-e8a0-45c4-87e0-0b6f612d1ff6","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":92.79,"gross_line_total":581.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":162.79,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"484565c9-4055-4e2e-9a24-150ca2773658","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":359.82,"gross_line_total":2255.28,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":631.26,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"2acb1625-e632-4c9c-ae31-8cc291c4dbed","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":126.03,"gross_line_total":789.96,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":221.11,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"3df61628-becb-40c0-aed5-cd0989b93ff2","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1277.06,"gross_line_total":8004.4,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":672.14,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"a49cf0a7-d529-4543-9eee-19df9024a544","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":107.91,"gross_line_total":676.35,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":189.31,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":"<p><strong>Footer:<\/strong> If you have any questions regarding this invoice, please contact us at [email\/phone number]. Thank you for your business! All payments should be made to [Company Name] via [Payment Methods, e.g., bank transfer, credit card, etc.]. Our banking details are listed above for your convenience.<\/p>","public_notes":null,"terms":"<p><strong>Terms:<\/strong> Payment is due within 30 days from the invoice date unless otherwise agreed in writing. Late payments may incur interest at a rate of [specify rate, e.g., 1.5% per month] from the due date. Please include the invoice number when making payments to ensure proper allocation.<\/p>","tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2163.150000","uses_inclusive_taxes":1,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":"2025-01-21 01:00:00","custom_surcharge1":"123.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"13548.150000","balance":"13548.150000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737373514,"updated_at":1737414252,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"4oeEWvmb0B","status":-2}',
        '{"assigned_user_id":null,"status_id":1,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0415","discount":"0.000000","is_amount_discount":true,"po_number":"surcharges inclusive taxes","date":"2025-01-20","last_sent_date":null,"due_date":null,"is_deleted":false,"line_items":[{"_id":"ee8fc6cc-7a56-4fca-a5fc-b842ef28d414","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1127.58,"tax_amount":180.03,"gross_line_total":1127.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":315.85,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"bbb5090d-094e-46e3-b545-0d0569f3deb1","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":92.86,"gross_line_total":581.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":162.91,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"47150725-8659-4c77-b3b1-425a9ddfaf41","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":360.09,"gross_line_total":2255.28,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":631.73,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"e6f1dd96-6342-4090-83f1-2db8cf7a1d70","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":126.13,"gross_line_total":789.96,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":221.28,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"0febc16b-5e6b-4105-bb73-00fef81f0c56","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1278.01,"gross_line_total":8004.4,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":672.64,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"d9256048-3fa5-43e7-b09f-160c8ac39de6","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":107.99,"gross_line_total":676.35,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":189.45,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":"<p><strong>Footer:<\/strong> If you have any questions regarding this invoice, please contact us at [email\/phone number]. Thank you for your business! All payments should be made to [Company Name] via [Payment Methods, e.g., bank transfer, credit card, etc.]. Our banking details are listed above for your convenience.<\/p>","public_notes":null,"terms":"<p><strong>Terms:<\/strong> Payment is due within 30 days from the invoice date unless otherwise agreed in writing. Late payments may incur interest at a rate of [specify rate, e.g., 1.5% per month] from the due date. Please include the invoice number when making payments to ensure proper allocation.<\/p>","tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2164.750000","uses_inclusive_taxes":1,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":null,"custom_surcharge1":"123.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"13558.150000","balance":"0.000000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737354726,"updated_at":1737363334,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"BDbDWrAal2","status":1}',
        '{"assigned_user_id":null,"status_id":4,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0414","discount":"0.000000","is_amount_discount":true,"po_number":"surcharges","date":"2025-01-20","last_sent_date":null,"due_date":"2025-02-19","is_deleted":false,"line_items":[{"_id":"8e1f8f95-71a1-4d95-b042-2fb82527bcb0","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1127.58,"tax_amount":214.24,"gross_line_total":1341.82,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":312.81,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"dfe998b1-ded4-441a-b545-14fc59bb2b23","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":110.5,"gross_line_total":692.08,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":162.79,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"04779abc-f6f0-430a-96b6-c461834ca126","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":428.5,"gross_line_total":2683.78,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":631.26,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"b46fe9ba-57cd-4a47-bec4-b26d152073ad","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":150.09,"gross_line_total":940.0500000000001,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":221.11,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"9e08da6b-6c63-4977-a7cf-5a4874173956","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1520.84,"gross_line_total":9525.24,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":672.14,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"8dc7574d-9177-4857-9917-f031b95669b9","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":128.51,"gross_line_total":804.86,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":189.31,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2576.050000","uses_inclusive_taxes":0,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":null,"custom_surcharge1":"123.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"16134.200000","balance":"0.000000","partial":null,"partial_due_date":null,"last_viewed":null,"created_at":1737353147,"updated_at":1737414241,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"16134.200000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"46dB9p2b79","status":4}',
        '{"assigned_user_id":null,"status_id":1,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0413","discount":"10.000000","is_amount_discount":true,"po_number":"Exclusive TAXES +DISC+amount+linedisc","date":"2025-01-20","last_sent_date":null,"due_date":null,"is_deleted":false,"line_items":[{"_id":"e85b0c8c-c6ef-491a-a3a1-f9bb5748f9db","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":10,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1117.58,"tax_amount":212.18,"gross_line_total":1329.76,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":312.81,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"d42329c4-bf31-4420-96da-0afdcc88f8d8","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":110.42,"gross_line_total":692,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":162.79,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"1186564d-2084-41c9-aa7e-049e315125bd","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":428.18,"gross_line_total":2683.46,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":631.26,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"bd5f8f63-bbdb-4e00-8c10-31bf6025ae83","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":149.98,"gross_line_total":939.94,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":221.11,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"4f9744b4-df4b-4822-b46d-03cf619fb2e2","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1519.7,"gross_line_total":9524.1,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":672.14,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"57898c16-28ca-4f20-9576-564bb3f4a829","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":128.41,"gross_line_total":804.76,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":189.31,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2548.870000","uses_inclusive_taxes":0,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":null,"custom_surcharge1":"0.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"15964.020000","balance":"0.000000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737351493,"updated_at":1737417393,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"KQe13pobJY","status":1}',
        '{"assigned_user_id":null,"status_id":2,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0412","discount":"10.000000","is_amount_discount":false,"po_number":"Exclusive TAXES +DISC+perc+linedisc","date":"2025-01-20","last_sent_date":null,"due_date":"2025-02-19","is_deleted":false,"line_items":[{"_id":"719edafa-25bc-4245-a09d-95e9e643e237","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":10,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1014.82,"tax_amount":173.53,"gross_line_total":1188.3500000000001,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":312.81,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"6fedc57a-0fdc-4b52-8465-e3a884d78ffa","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":99.45,"gross_line_total":681.0300000000001,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":162.79,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"58294dae-eb4a-41b1-a925-80404cb4ea38","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":385.65,"gross_line_total":2640.9300000000003,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":631.26,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"5cb2f313-1c27-4f7e-889a-c9651e6e7b4a","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":135.08,"gross_line_total":925.0400000000001,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":221.11,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"44c86f63-3b1b-4f5a-8a3a-9c0afa95eda4","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1368.75,"gross_line_total":9373.15,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":672.14,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"eb059361-dffe-443f-8f01-acaa986eb641","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":115.66,"gross_line_total":792.01,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":189.31,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2278.120000","uses_inclusive_taxes":0,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":"2025-01-21 01:00:00","custom_surcharge1":"0.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"14268.270000","balance":"14268.270000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737351156,"updated_at":1737417445,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"oQeZrGEepZ","status":-2}',
        '{"assigned_user_id":null,"status_id":1,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0411","discount":"10.000000","is_amount_discount":true,"po_number":"INCLUSIVE TAXES +DISC+amount+linedisc","date":"2025-01-20","last_sent_date":null,"due_date":null,"is_deleted":false,"line_items":[{"_id":"37e101e4-9462-460b-b9e3-87188da13f7d","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":10,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1117.58,"tax_amount":178.3,"gross_line_total":1117.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":312.81,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"e5d25491-8a17-42ac-91f9-359b8d0af9b1","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":92.79,"gross_line_total":581.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":162.79,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"eb4499af-0c18-44ea-a20e-b31c654bf519","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":359.82,"gross_line_total":2255.28,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":631.26,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"1a9942be-503a-463a-9a95-47d169b8cd12","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":126.03,"gross_line_total":789.96,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":221.11,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"198d88c7-ab59-4544-8090-00afcee73382","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1277.06,"gross_line_total":8004.4,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":672.14,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"84bb0eab-3fe1-462e-92e2-e5229e362cec","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":107.91,"gross_line_total":676.35,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":189.31,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2141.910000","uses_inclusive_taxes":1,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":null,"custom_surcharge1":"0.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"13415.150000","balance":"0.000000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737351131,"updated_at":1737417505,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"4QbYqY9bzq","status":1}',
        '{"assigned_user_id":null,"status_id":1,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0410","discount":"10.000000","is_amount_discount":false,"po_number":"INCLUSIVE TAXES +DISC+per+linedisc","date":"2025-01-20","last_sent_date":null,"due_date":null,"is_deleted":false,"line_items":[{"_id":"1437777f-e583-4b2c-8343-b281929fcb84","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":10,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1014.82,"tax_amount":"145.83","gross_line_total":1014.82,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":255.84,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"f318d9e2-6571-487c-9165-384356646988","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":"83.57","gross_line_total":581.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":146.62,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"77452b0e-310d-4d7d-9d5c-0524d159fbb6","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":"324.08","gross_line_total":2255.28,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":568.56,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"de8b0d05-63cf-4015-9e66-5fa13d24ad45","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":"113.52","gross_line_total":789.96,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":199.15,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"6d431838-185e-406a-b643-08145a92c5af","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":"1150.21","gross_line_total":8004.4,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":605.38,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"94bc670f-72d0-441b-af9b-79ab60a7bbf8","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":"97.19","gross_line_total":676.35,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":170.51,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"1914.400000","uses_inclusive_taxes":1,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":null,"custom_surcharge1":"0.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"11990.150000","balance":"0.000000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737351110,"updated_at":1737417533,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"QBeXpXleyK","status":1}',
        '{"assigned_user_id":null,"status_id":1,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0409","discount":"10.000000","is_amount_discount":true,"po_number":"INCLUSIVE TAXES +DISC+amount","date":"2025-01-20","last_sent_date":null,"due_date":null,"is_deleted":false,"line_items":[{"_id":"188776d4-ea51-416e-9e55-8cb21008bad3","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":10,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1117.58,"tax_amount":178.3,"gross_line_total":1117.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":312.81,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"5b347e3b-9e54-4720-9a97-07aed93e9ad2","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":92.79,"gross_line_total":581.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":162.79,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"01b623da-6fa8-484e-b80c-ee9ade330720","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":359.82,"gross_line_total":2255.28,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":631.26,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"477bd5e7-9e03-4cf6-a26c-cf19f606cf47","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":126.03,"gross_line_total":789.96,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":221.11,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"6b3bd92f-8f47-4ad9-b953-223a50f5bff0","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1277.06,"gross_line_total":8004.4,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":672.14,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"720e9975-881a-43cd-b0c4-13d2afe1037b","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":true,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":107.91,"gross_line_total":676.35,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":189.31,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2141.910000","uses_inclusive_taxes":1,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":null,"custom_surcharge1":"0.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"13415.150000","balance":"0.000000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737351038,"updated_at":1737417629,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"lNbWoKXbyg","status":1}',
        '{"assigned_user_id":null,"status_id":2,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0408","discount":"10.000000","is_amount_discount":false,"po_number":"INCLUSIVE TAXES +DISC","date":"2025-01-20","last_sent_date":null,"due_date":"2025-02-19","is_deleted":false,"line_items":[{"_id":"1f240fb7-5b2a-41fd-823f-18f72f7821b0","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1127.58,"tax_amount":"162.03","gross_line_total":1127.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":284.26,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"b3db4c01-159c-44ca-89de-68a0a8fe32ba","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":"83.57","gross_line_total":581.58,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":146.62,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"34fe45ed-c343-4d2e-9453-4d3941dab014","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":"324.08","gross_line_total":2255.28,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":568.56,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"8a47d310-651a-4915-8d9a-5acdf0ecf1af","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"3","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":"113.52","gross_line_total":789.96,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":199.15,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"bf43fe30-3126-463e-a252-d8a44a05c5cc","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":"1150.21","gross_line_total":8004.4,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":605.38,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"bea7de26-b434-4c1d-8944-ce0b45fd3b59","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":"97.19","gross_line_total":676.35,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":170.51,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"1930.600000","uses_inclusive_taxes":1,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":"2025-01-21 01:00:00","custom_surcharge1":"0.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"12091.630000","balance":"12091.630000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737342681,"updated_at":1737417655,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"y5eVn29eEP","status":-2}',
        '{"assigned_user_id":null,"status_id":2,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0407","discount":"0.000000","is_amount_discount":false,"po_number":"INCLUSIVE TAXES","date":"2025-01-20","last_sent_date":null,"due_date":"2025-02-19","is_deleted":false,"line_items":[{"_id":"83c232de-6564-44df-9c57-5a19a0f3f3a7","quantity":3,"cost":375.86,"product_key":"<b>Adresse des travaux<\/b><\/br>Address of works","product_cost":0,"notes":"34343","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1127.58,"tax_amount":214.24,"gross_line_total":1341.82,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":315.85,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"de17bee4-0bee-4816-9a3e-c5f8bed14754","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":110.5,"gross_line_total":692.08,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":162.91,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"802af6a4-ba3d-4edf-8f08-b0ffc16e1e66","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":428.5,"gross_line_total":2683.78,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":631.73,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"62f59019-fa78-4ecd-a2ed-fc644f6c72fd","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":150.09,"gross_line_total":940.0500000000001,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":221.28,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"b093f33b-4dc2-4d4f-956f-99d3e266a427","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1520.84,"gross_line_total":9525.24,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":672.64,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"94d43d38-a3b0-4d81-adfc-52749f8993c2","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":128.51,"gross_line_total":804.86,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":189.45,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"1c0b145f-74d5-443b-8d47-66c35d3c3136","quantity":1,"cost":0,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"MwSt.","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":0,"tax_amount":0,"gross_line_total":0,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"1","net_cost":0,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2552.680000","uses_inclusive_taxes":0,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":"2025-01-21 01:00:00","custom_surcharge1":"0.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"15987.830000","balance":"15987.830000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737338859,"updated_at":1737417694,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"APdRjK0dGy","status":-2}',
        '{"assigned_user_id":null,"status_id":1,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0406","discount":"5.000000","is_amount_discount":false,"po_number":"TOTAL DISCOUNT","date":"2025-01-20","last_sent_date":null,"due_date":null,"is_deleted":false,"line_items":[{"_id":"6ac927ac-dbef-4dc8-91b9-a79741e272b3","quantity":3,"cost":375.86,"product_key":"","product_cost":0,"notes":"34343","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1127.58,"tax_amount":203.53,"gross_line_total":1331.11,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":294.12,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"e6c11a74-5ff8-49df-aa33-66579028db08","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":104.98,"gross_line_total":686.5600000000001,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":143.8,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"f5dd855e-a0d0-4203-84f5-99bffe9be095","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":407.08,"gross_line_total":2662.36,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":582.14,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"c7a4c232-8b0d-41d9-90b1-d3651a3f7f72","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":142.59,"gross_line_total":932.5500000000001,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":0,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"250d9086-90f1-4db6-8698-574f77446ee1","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1444.79,"gross_line_total":9449.189999999999,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":593.74,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"89d38e95-2f50-4536-8567-76d2ac43827c","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":122.08,"gross_line_total":798.4300000000001,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":176.42,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2425.050000","uses_inclusive_taxes":0,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":null,"custom_surcharge1":"0.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"15188.440000","balance":"0.000000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737331244,"updated_at":1737417740,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"YqaQgKqdnj","status":1}',
        '{"assigned_user_id":null,"status_id":1,"project_id":null,"vendor_id":null,"recurring_id":null,"design_id":2,"number":"V2025-0405","discount":"0.000000","is_amount_discount":false,"po_number":"NO DISCOUNTS","date":"2025-01-20","last_sent_date":null,"due_date":null,"is_deleted":false,"line_items":[{"_id":"35b17be0-e101-473c-a85a-979a923c7571","quantity":3,"cost":375.86,"product_key":"","product_cost":0,"notes":"34343","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":1127.58,"tax_amount":214.24,"gross_line_total":1341.82,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":294.12,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"c9f21206-0980-4fd9-912d-7186822dd0c5","quantity":3,"cost":193.86,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":581.58,"tax_amount":110.5,"gross_line_total":692.08,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":143.8,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"a7f5eff6-e569-4eb7-889a-456f24527121","quantity":3,"cost":751.76,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":2255.28,"tax_amount":428.5,"gross_line_total":2683.78,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":582.14,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"da55da19-7cbd-4864-b2a4-7dcadc70c7b1","quantity":3,"cost":263.32,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":789.96,"tax_amount":150.09,"gross_line_total":940.0500000000001,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":0,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"aab699dc-2173-411b-a29d-8b00bc220399","quantity":10,"cost":800.44,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":8004.4,"tax_amount":1520.84,"gross_line_total":9525.24,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":593.74,"task_id":"","expense_id":"","unit_code":"C62"},{"_id":"494d53fb-51de-4f83-9d09-07c30baa3dfc","quantity":3,"cost":225.45,"product_key":"","product_cost":0,"notes":"","discount":0,"is_amount_discount":false,"tax_name1":"mwst","tax_rate1":19,"tax_name2":"","tax_rate2":0,"tax_name3":"","tax_rate3":0,"sort_id":"0","line_total":676.35,"tax_amount":128.51,"gross_line_total":804.86,"date":"","custom_value1":"","custom_value2":"","custom_value3":"","custom_value4":"","type_id":"1","tax_id":"7","net_cost":176.42,"task_id":"","expense_id":"","unit_code":"C62"}],"backup":null,"footer":null,"public_notes":null,"terms":null,"tax_name1":"","tax_rate1":"0.000000","tax_name2":"","tax_rate2":"0.000000","tax_name3":"","tax_rate3":"0.000000","total_taxes":"2552.680000","uses_inclusive_taxes":0,"custom_value1":null,"custom_value2":null,"custom_value3":null,"custom_value4":null,"next_send_date":null,"custom_surcharge1":"0.000000","custom_surcharge2":"0.000000","custom_surcharge3":"0.000000","custom_surcharge4":"0.000000","custom_surcharge_tax1":false,"custom_surcharge_tax2":false,"custom_surcharge_tax3":false,"custom_surcharge_tax4":false,"exchange_rate":"0.945332","amount":"15987.830000","balance":"0.000000","partial":"0.000000","partial_due_date":null,"last_viewed":null,"created_at":1737331204,"updated_at":1737417787,"deleted_at":null,"reminder1_sent":null,"reminder2_sent":null,"reminder3_sent":null,"reminder_last_sent":null,"auto_bill_enabled":1,"paid_to_date":"0.000000","subscription_id":null,"auto_bill_tries":0,"is_proforma":0,"tax_data":null,"e_invoice":null,"sync":null,"gateway_fee":"0.000000","hashed_id":"WZdPWKlbKg","status":1}',
    ];

    private string $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

    private string $zf_extended_wl = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_EXTENDED.xslt';

    private string $extended_profile = 'XInvoice-Extended';

    protected function setUp(): void
    {
        parent::setUp();


        if (config('ninja.testvars.travis')) {
            $this->markTestSkipped("do not run in CI");
        }

        $this->withoutMiddleware(
            ThrottleRequests::class
        );


        $this->makeTestData();

    }


    private function setupTestData(array $params = []): array
    {

        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? 'DE123456789';
        $settings->id_number = $params['company_id_number'] ?? '';
        $settings->classification = $params['company_classification'] ?? 'business';
        $settings->country_id = Country::where('iso_3166_2', 'DE')->first()->id;
        $settings->email = $this->faker->safeEmail();
        $settings->e_invoice_type = $params['e_invoice_type'] ?? 'XInvoice_3_0';
        $settings->currency_id = '3';
        $settings->name = 'Test Company';
        $settings->address1 = 'Line 1 of address of the seller';
        // $settings->address2 = 'Line 2 of address of the seller';
        $settings->city = 'Hamburg';
        // $settings->state = 'Berlin';
        $settings->postal_code = 'X123433';
        $settings->enable_e_invoice = true;

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = $params['over_threshold'] ?? false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = $params['company_country'] ?? 'DE';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC

        $pfa = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';

        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;

        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';

        $pm->PaymentMeansCode = $pmc;

        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $this->company->settings = $settings;
        $this->company->tax_data = $tax_data;
        $this->company->calculate_taxes = true;
        $this->company->legal_entity_id = 290868;
        $this->company->e_invoice = $stub;
        $this->company->save();
        $company = $this->company;

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', $params['client_country'] ?? 'FR')->first()->id,
            'vat_number' => $params['client_vat'] ?? '',
            'classification' => $params['classification'] ?? 'individual',
            'has_valid_vat_number' => $params['has_valid_vat'] ?? false,
            'name' => 'Test Client',
            'is_tax_exempt' => $params['is_tax_exempt'] ?? false,
            'id_number' => $params['client_id_number'] ?? '',
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail()
        ]);

        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ]);

        $items = $invoice->line_items;
        foreach ($items as &$item) {
            $item->tax_name2 = '';
            $item->tax_rate2 = 0;
            $item->tax_name3 = '';
            $item->tax_rate3 = 0;
            $item->uses_inclusive_taxes = false;
        }
        unset($item);

        $invoice->line_items = array_values($items);
        $invoice = $invoice->calc()->getInvoice();

        return compact('company', 'client', 'invoice');
    }


    public function testDeTodeTaxExempt()
    {

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];

        $repo = new InvoiceRepository();

        foreach ($this->inclusive_scenarios as $scenario) {

            $invoice_data = json_decode($scenario, true);

            $line_items = $invoice_data['line_items'];

            foreach ($line_items as &$item) {
                $item['tax_rate1'] = 0;
                $item['tax_name1'] = 'VAT';
                $item['tax_id'] = '5';
            }
            unset($item);

            $invoice_data['line_items'] = array_values($line_items);

            $invoice_data['uses_inclusive_taxes'] = false;

            unset($invoice_data['hashed_id']);
            unset($invoice_data['status']);
            $invoice = $repo->save($invoice_data, $invoice);
            $invoice = $invoice->calc()->getInvoice();

            $xml = $invoice->service()->getEInvoice();

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
            $validator->setStyleSheets([$this->zug_16931]);
            $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
            $validator->validate();

            if (count($validator->getErrors()) > 0) {

                nlog($invoice->withoutRelations()->toArray());
                nlog($xml);
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());

        }
    }


    public function testDeTodeTaxExemptExtendedProfile()
    {

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];

        $repo = new InvoiceRepository();

        foreach ($this->inclusive_scenarios as $scenario) {

            $invoice_data = json_decode($scenario, true);

            $line_items = $invoice_data['line_items'];

            foreach ($line_items as &$item) {
                $item['tax_rate1'] = 0;
                $item['tax_name1'] = 'VAT';
                $item['tax_id'] = '5';
            }
            unset($item);

            $invoice_data['line_items'] = array_values($line_items);

            $invoice_data['uses_inclusive_taxes'] = false;

            unset($invoice_data['hashed_id']);
            unset($invoice_data['status']);
            $invoice = $repo->save($invoice_data, $invoice);
            $invoice = $invoice->calc()->getInvoice();

            $xml = $invoice->service()->getEInvoice();

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
            $validator->setStyleSheets([$this->zf_extended_wl]);
            $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
            $validator->validate();

            if (count($validator->getErrors()) > 0) {

                nlog($invoice->withoutRelations()->toArray());
                nlog($xml);
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());

        }
    }

    public function testDeToNlReverseTax()
    {

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'NL',
            'client_vat' => 'NL808436332B01',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];

        $repo = new InvoiceRepository();

        foreach ($this->inclusive_scenarios as $scenario) {

            $invoice_data = json_decode($scenario, true);

            $line_items = $invoice_data['line_items'];

            foreach ($line_items as &$item) {
                $item['tax_rate1'] = 0;
                $item['tax_name1'] = '';
                $item['tax_id'] = '9';
            }
            unset($item);

            $invoice_data['line_items'] = array_values($line_items);

            $invoice_data['uses_inclusive_taxes'] = false;

            unset($invoice_data['hashed_id']);
            unset($invoice_data['status']);

            $invoice = $repo->save($invoice_data, $invoice);
            $invoice = $invoice->calc()->getInvoice();

            $xml = $invoice->service()->getEInvoice();

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
            $validator->setStyleSheets([$this->zug_16931]);
            $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
            $validator->validate();

            if (count($validator->getErrors()) > 0) {

                nlog($invoice->withoutRelations()->toArray());
                nlog($xml);
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());


        }
    }


    public function testInclusiveScenarios()
    {

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $repo = new InvoiceRepository();

        foreach ($this->inclusive_scenarios as $scenario) {

            $invoice_data = json_decode($scenario, true);
            unset($invoice_data['hashed_id']);
            unset($invoice_data['status']);
            $invoice = $repo->save($invoice_data, $invoice);
            $invoice = $invoice->calc()->getInvoice();

            $xml = $invoice->service()->getEInvoice();

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
            $validator->setStyleSheets([$this->zug_16931]);
            $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
            $validator->validate();

            if (count($validator->getErrors()) > 0) {

                nlog($invoice->withoutRelations()->toArray());
                nlog($xml);
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());


        }
    }


    public function testExclusiveScenarios()
    {

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $repo = new InvoiceRepository();

        foreach ($this->inclusive_scenarios as $scenario) {

            $invoice_data = json_decode($scenario, true);

            $invoice_data['uses_inclusive_taxes'] = false;

            unset($invoice_data['hashed_id']);
            unset($invoice_data['status']);
            $invoice = $repo->save($invoice_data, $invoice);
            $invoice = $invoice->calc()->getInvoice();

            $xml = $invoice->service()->getEInvoice();

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
            $validator->setStyleSheets([$this->zug_16931]);
            $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
            $validator->validate();

            if (count($validator->getErrors()) > 0) {

                // nlog($invoice->toArray());
                // nlog($xml);
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());


        }
    }


    public function testZugFerdValidation()
    {



        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testZugFerdValidationWithInclusiveTaxes()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testZugFerdValidationWithInclusiveTaxesAndTotalAmountDiscount()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount = 20;
        $invoice->is_amount_discount = true;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testZugFerdValidationWithInclusiveTaxesAndTotalPercentDiscount()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount = 20;
        $invoice->is_amount_discount = false;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }

    public function testZugFerdValidationWithInclusiveTaxesAndTotalPercentDiscountOnLineItemsAlso()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';
        // $xr_cii = 'Services/EDocument/Standards/Validation/Zugferd/xrechnung_cii.xslt';

        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount = 20;
        $invoice->is_amount_discount = false;

        $items = $invoice->line_items;

        foreach ($items as &$item) {
            $item->discount = 10;
            $item->is_amount_discount = false;
        }
        unset($item);

        $invoice->line_items = $items;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zug_16931]);


        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testZugFerdValidationWithInclusiveTaxesAndTotalAmountDiscountOnLineItemsAlso()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount = 20;
        $invoice->is_amount_discount = true;

        $items = $invoice->line_items;

        foreach ($items as &$item) {
            $item->discount = 5;
            $item->is_amount_discount = true;
        }
        unset($item);

        $invoice->line_items = $items;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }

    // ============================================================================
    // EXTENDED PROFILE TEST METHODS - Duplicates using extended profile and XSLT
    // ============================================================================

    public function testDeToNlReverseTaxExtendedProfile()
    {

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'NL',
            'client_vat' => 'NL808436332B01',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];

        $repo = new InvoiceRepository();

        foreach ($this->inclusive_scenarios as $scenario) {

            $invoice_data = json_decode($scenario, true);

            $line_items = $invoice_data['line_items'];

            foreach ($line_items as &$item) {
                $item['tax_rate1'] = 0;
                $item['tax_name1'] = '';
                $item['tax_id'] = '9';
            }
            unset($item);

            $invoice_data['line_items'] = array_values($line_items);

            $invoice_data['uses_inclusive_taxes'] = false;

            unset($invoice_data['hashed_id']);
            unset($invoice_data['status']);
            $invoice = $repo->save($invoice_data, $invoice);
            $invoice = $invoice->calc()->getInvoice();

            $xml = $invoice->service()->getEInvoice();

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
            $validator->setStyleSheets([$this->zf_extended_wl]);
            $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
            $validator->validate();

            if (count($validator->getErrors()) > 0) {

                nlog($invoice->withoutRelations()->toArray());
                nlog($xml);
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());

        }
    }

    public function testInclusiveScenariosExtendedProfile()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];

        $repo = new InvoiceRepository();

        foreach ($this->inclusive_scenarios as $scenario) {

            $invoice_data = json_decode($scenario, true);

            unset($invoice_data['hashed_id']);
            unset($invoice_data['status']);
            $invoice = $repo->save($invoice_data, $invoice);
            $invoice = $invoice->calc()->getInvoice();

            $xml = $invoice->service()->getEInvoice();

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
            $validator->setStyleSheets([$this->zf_extended_wl]);
            $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
            $validator->validate();

            if (count($validator->getErrors()) > 0) {

                nlog($invoice->withoutRelations()->toArray());
                nlog($xml);
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());

        }
    }

    public function testExclusiveScenariosExtendedProfile()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];

        $repo = new InvoiceRepository();

        foreach ($this->inclusive_scenarios as $scenario) {

            $invoice_data = json_decode($scenario, true);

            $invoice_data['uses_inclusive_taxes'] = false;

            unset($invoice_data['hashed_id']);
            unset($invoice_data['status']);
            $invoice = $repo->save($invoice_data, $invoice);
            $invoice = $invoice->calc()->getInvoice();

            $xml = $invoice->service()->getEInvoice();

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
            $validator->setStyleSheets([$this->zf_extended_wl]);
            $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
            $validator->validate();

            if (count($validator->getErrors()) > 0) {

                nlog($invoice->withoutRelations()->toArray());
                nlog($xml);
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());

        }
    }

    public function testZugFerdValidationExtendedProfile()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zf_extended_wl]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());
    }

    public function testZugFerdValidationWithInclusiveTaxesExtendedProfile()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zf_extended_wl]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());
    }

    public function testZugFerdValidationWithInclusiveTaxesAndTotalAmountDiscountExtendedProfile()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount = 20;
        $invoice->is_amount_discount = true;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zf_extended_wl]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());
    }

    public function testZugFerdValidationWithInclusiveTaxesAndTotalPercentDiscountExtendedProfile()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount = 20;
        $invoice->is_amount_discount = false;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zf_extended_wl]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());
    }

    public function testZugFerdValidationWithInclusiveTaxesAndTotalPercentDiscountOnLineItemsAlsoExtendedProfile()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount = 20;
        $invoice->is_amount_discount = false;

        $items = $invoice->line_items;

        foreach ($items as &$item) {
            $item->discount = 10;
            $item->is_amount_discount = false;
        }
        unset($item);

        $invoice->line_items = $items;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zf_extended_wl]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());
    }

    public function testZugFerdValidationWithInclusiveTaxesAndTotalAmountDiscountOnLineItemsAlsoExtendedProfile()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'e_invoice_type' => $this->extended_profile,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount = 20;
        $invoice->is_amount_discount = true;

        $items = $invoice->line_items;

        foreach ($items as &$item) {
            $item->discount = 5;
            $item->is_amount_discount = true;
        }
        unset($item);

        $invoice->line_items = $items;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$this->zf_extended_wl]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());
    }


}
