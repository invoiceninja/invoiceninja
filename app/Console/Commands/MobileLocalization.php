<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DbServer;
use App\Models\User;
use App\Models\Company;
use App\Libraries\CurlUtils;

class MobileLocalization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:mobile-localization {--type=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate mobile localization resources';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $type = strtolower($this->option('type'));

        switch ($type) {
            case 'laravel':
                $this->laravelResources();
                break;
            default:
                $this->flutterResources();
                break;
        }
    }

    private function laravelResources()
    {
        $resources = $this->getResources();

        foreach ($resources as $key => $val) {
            $transKey = "texts.{$key}";
            if (trans($transKey) == $transKey) {
                echo "'$key' => '$val',\n";
            }
        }
    }

    private function flutterResources()
    {
        echo('flutter');
    }

    private function getResources()
    {
        $url = 'https://raw.githubusercontent.com/invoiceninja/flutter-mobile/develop/lib/utils/localization.dart';
        $data = CurlUtils::get($url);

        $start = strpos($data, '\'en\': {') + 8;
        $end = strpos($data, '},', $start);
        $data = substr($data, $start, $end - $start - 6);

        $data = str_replace("\n", "", $data);
        $data = str_replace("'", "\"", $data);

        //$data = '"billing_address": "Billing Address",      "shipping_address": "Shipping Address",      "total_revenue": "Total Revenue",      "average_invoice": "Average Invoice",      "outstanding": "Outstanding",      "invoices_sent": "Invoices Sent",      "active_clients": "Active Clients",      "close": "Close",      "email": "Email",      "password": "Password",      "url": "URL",      "secret": "Secret",      "name": "Name",      "log_out": "Log Out",      "login": "Login",      "filter": "Filter",      "sort": "Sort",      "search": "Search",      "active": "Active",      "archived": "Archived",      "deleted": "Deleted",      "dashboard": "Dashboard",      "archive": "Archive",      "delete": "Delete",      "restore": "Restore",      "refresh_complete": "Refresh Complete",      "please_enter_your_email": "Please enter your email",      "please_enter_your_password": "Please enter your password",      "please_enter_your_url": "Please enter your URL",      "please_enter_a_product_key": "Please enter a product key",      "ascending": "Ascending",      "descending": "Descending",      "save": "Save",      "an_error_occurred": "An error occurred",      "paid_to_date": "Paid to Date",      "balance_due": "Balance Due",      "balance": "Balance",      "overview": "Overview",      "details": "Details",      "phone": "Phone",      "website": "Website",      "vat_number": "VAT Number",      "id_number": "Id Number",      "create": "Create",      "copied_to_clipboard": "Copied :value to the clipboard",      "error": "Error",      "could_not_launch": "Could not launch",      "contacts": "Contacts",      "additional": "Additional",      "first_name": "First Name",      "last_name": "Last Name",      "add_contact": "Add Contact",      "are_you_sure": "Are you sure?",      "cancel": "Cancel",      "ok": "Ok",      "remove": "Remove",      "email_is_invalid": "Email is invalid",      "product": "Product",      "products": "Products",      "new_product": "New Product",      "successfully_created_product": "Successfully created product",      "successfully_updated_product": "Successfully updated product",      "successfully_archived_product": "Successfully archived product",      "successfully_deleted_product": "Successfully deleted product",      "successfully_restored_product": "Successfully restored product",      "product_key": "Product",      "notes": "Notes",      "cost": "Cost",      "client": "Client",      "clients": "Clients",      "new_client": "New Client",      "successfully_created_client": "Successfully created client",      "successfully_updated_client": "Successfully updated client",      "successfully_archived_client": "Successfully archived client",      "successfully_deleted_client": "Successfully deleted client",      "successfully_restored_client": "Successfully restored client",      "address1": "Street",      "address2": "Apt/Suite",      "city": "City",      "state": "State/Province",      "postal_code": "Postal Code",      "country": "Country",      "invoice": "Invoice",      "invoices": "Invoices",      "new_invoice": "New Invoice",      "successfully_created_invoice": "Successfully created invoice",      "successfully_updated_invoice": "Successfully updated invoice",      "successfully_archived_invoice": "Successfully archived invoice",      "successfully_deleted_invoice": "Successfully deleted invoice",      "successfully_restored_invoice": "Successfully restored invoice",      "successfully_emailed_invoice": "Successfully emailed invoice",      "amount": "Amount",      "invoice_number": "Invoice Number",      "invoice_date": "Invoice Date",      "discount": "Discount",      "po_number": "PO Number",      "terms": "Terms",      "public_notes": "Public Notes",      "private_notes": "Private Notes",      "frequency": "Frequency",      "start_date": "Start Date",      "end_date": "End Date",      "quote_number": "Quote Number",      "quote_date": "Quote Date",      "valid_until": "Valid Until",      "items": "Items",      "partial_deposit": "Partial/Deposit",      "description": "Description",      "unit_cost": "Unit Cost",      "quantity": "Quantity",      "add_item": "Add Item",      "contact": "Contact",      "work_phone": "Phone",      "total_amount": "Total Amount",      "pdf": "PDF",      "due_date": "Due Date",      "partial_due_date": "Partial Due Date",      "status": "Status",      "invoice_status_id": "Invoice Status",      "click_plus_to_add_item": "Click + to add an item",      "count_selected": ":count selected",      "total": "Total",      "percent": "Percent",      "edit": "Edit",      "dismiss": "Dismiss",      "please_select_a_date": "Please select a date",      "please_select_a_client": "Please select a client",      "task_rate": "Task Rate",      "settings": "Settings",      "language": "Language",      "currency": "Currency",      "created_at": "Created",      "updated_at": "Updated",      "tax": "Tax",      "please_enter_an_invoice_number": "Please enter an invoice number",      "please_enter_a_quote_number": "Please enter a quote number",      "clients_invoices": ":client\"s invoices",      "past_due": "Past Due",      "draft": "Draft",      "sent": "Sent",      "viewed": "Viewed",      "approved": "Approved",      "partial": "Partial",      "paid": "Paid",      "invoice_status_1": "Draft",      "invoice_status_2": "Sent",      "invoice_status_3": "Viewed",      "invoice_status_4": "Approved",      "invoice_status_5": "Partial",      "invoice_status_6": "Paid",      "mark_sent": "Mark Sent",      "successfully_marked_invoice_as_sent":          "Successfully marked invoice as sent",      "done": "Done",      "please_enter_a_client_or_contact_name":          "Please enter a client or contact name",      "dark_mode": "Dark Mode",      "restart_app_to_apply_change": "Restart the app to apply the change",      "refresh_data": "Refresh Data",      "blank_contact": "Blank Contact",      "activity": "Activity",      "no_records_found": "No records found",      "clone": "Clone",      "loading": "Loading",      "industry": "Industry",      "size": "Size",      "payment_terms": "Payment Terms",      "net": "Net",      "client_portal": "Client Portal",      "show_tasks": "Show tasks",      "email_reminders": "Email Reminders",      "enabled": "Enabled",      "recipients": "Recipients",      "initial_email": "Initial Email",      "first_reminder": "First Reminder",      "second_reminder": "Second Reminder",      "third_reminder": "Third Reminder",      "reminder1": "First Reminder",      "reminder2": "Second Reminder",      "reminder3": "Third Reminder",      "template": "Template",      "send": "Send",      "subject": "Subject",      "body": "Body",      "send_email": "Send Email",      "documents": "Documents",      "auto_billing": "Auto billing",      "button": "Button",      "preview": "Preview",      "customize": "Customize",      "history": "History",      "payment": "Payment",      "payments": "Payments",      "quote": "Quote",      "quotes": "Quotes",      "expense": "Expense",      "expenses": "Expenses",      "vendor": "Vendor",      "vendors": "Vendors",      "task": "Task",      "tasks": "Tasks",      "project": "Project",      "projects": "Projects",      "activity_1": ":user created client :client",      "activity_2": ":user archived client :client",      "activity_3": ":user deleted client :client",      "activity_4": ":user created invoice :invoice",      "activity_5": ":user updated invoice :invoice",      "activity_6": ":user emailed invoice :invoice to :contact",      "activity_7": ":contact viewed invoice :invoice",      "activity_8": ":user archived invoice :invoice",      "activity_9": ":user deleted invoice :invoice",      "activity_10": ":contact entered payment :payment for :invoice",      "activity_11": ":user updated payment :payment",      "activity_12": ":user archived payment :payment",      "activity_13": ":user deleted payment :payment",      "activity_14": ":user entered :credit credit",      "activity_15": ":user updated :credit credit",      "activity_16": ":user archived :credit credit",      "activity_17": ":user deleted :credit credit",      "activity_18": ":user created quote :quote",      "activity_19": ":user updated quote :quote",      "activity_20": ":user emailed quote :quote to :contact",      "activity_21": ":contact viewed quote :quote",      "activity_22": ":user archived quote :quote",      "activity_23": ":user deleted quote :quote",      "activity_24": ":user restored quote :quote",      "activity_25": ":user restored invoice :invoice",      "activity_26": ":user restored client :client",      "activity_27": ":user restored payment :payment",      "activity_28": ":user restored :credit credit",      "activity_29": ":contact approved quote :quote",      "activity_30": ":user created vendor :vendor",      "activity_31": ":user archived vendor :vendor",      "activity_32": ":user deleted vendor :vendor",      "activity_33": ":user restored vendor :vendor",      "activity_34": ":user created expense :expense",      "activity_35": ":user archived expense :expense",      "activity_36": ":user deleted expense :expense",      "activity_37": ":user restored expense :expense",      "activity_39": ":user cancelled payment :payment",      "activity_40": ":user refunded payment :payment",      "activity_41": "Payment :payment failed",      "activity_42": ":user created task :task",      "activity_43": ":user updated task :task",      "activity_44": ":user archived task :task",      "activity_45": ":user deleted task :task",      "activity_46": ":user restored task :task",      "activity_47": ":user updated expense :expense"';

        return json_decode('{' . $data . '}');
    }

    protected function getOptions()
    {
        return [
            ['type', null, InputOption::VALUE_OPTIONAL, 'Type', null],
        ];
    }

}
