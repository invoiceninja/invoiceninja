<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Template;

use App\Models\Task;
use App\Models\User;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Libraries\MultiDB;
use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Services\Email\AdminEmail;
use App\Services\Email\EmailObject;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class TemplateAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param array $ids The array of entity IDs
     * @param string $template The template id
     * @param string $entity The entity class name
     * @param int $user_id requesting the template
     * @param string $db The database name
     * @param bool $send_email Determines whether to send an email
     * 
     * @return void
     */
    public function __construct(public array $ids, 
                                private string $template, 
                                private string $entity, 
                                private int $user_id, 
                                private Company $company,
                                private string $db, 
                                private string $hash,
                                private bool $send_email = false)
    {
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {   
        // nlog("inside template action");

        MultiDB::setDb($this->db);

        $key = $this->resolveEntityString();

        $resource = $this->entity::query();

        $template = Design::withTrashed()->find($this->decodePrimaryKey($this->template));

        $template_service = new TemplateService($template);
        
        match($this->entity){
            Invoice::class => $resource->with('payments', 'client'),
            Quote::class => $resource->with('client'),
            Task::class => $resource->with('client'),
            Credit::class => $resource->with('client'),
            RecurringInvoice::class => $resource->with('client'),
            Project::class => $resource->with('client'),
            Expense::class => $resource->with('client'),
            Payment::class => $resource->with('invoices', 'client'),
        };

        $result = $resource->withTrashed()
            ->whereIn('id', $this->transformKeys($this->ids))
            ->where('company_id', $this->company->id)
            ->get();

        if($result->count() <= 1)
            $data[$key] = collect($result);
        else 
            $data[$key] = $result;

        $ts = $template_service->build($data);
        
        // nlog($ts->getHtml());

        if($this->send_email) {
            $pdf = $ts->getPdf();
            $this->sendEmail($pdf, $template);
        }
        else {
            $pdf = $ts->getPdf();
            $filename = "templates/{$this->hash}.pdf";
            Storage::disk(config('filesystems.default'))->put($filename, $pdf);
            return $pdf;
        }
    }

    private function sendEmail(mixed $pdf, Design $template)
    {
        $user = $this->user_id ? User::find($this->user_id) : $this->company->owner();

        $template_name = " [{$template->name}]";
        $email_object = new EmailObject;
        $email_object->to = [new Address($user->email, $user->present()->name())];
        $email_object->attachments = [['file' => base64_encode($pdf), 'name' => ctrans('texts.template') . ".pdf"]];
        $email_object->company_key = $this->company->company_key;
        $email_object->company = $this->company;
        $email_object->settings = $this->company->settings;
        $email_object->logo = $this->company->present()->logo();
        $email_object->whitelabel = $this->company->account->isPaid() ? true : false;
        $email_object->user_id = $user->id;
        $email_object->text_body = ctrans('texts.download_report_description') . $template_name;
        $email_object->body = ctrans('texts.download_report_description') . $template_name;
        $email_object->subject = ctrans('texts.download_report_description') . $template_name;

        (new AdminEmail($email_object, $this->company))->handle();
    }

    /**
     * Context
     * 
     * If I have an array of invoices, what could I possib
     * 
     * 
     */
    private function resolveEntityString()
    {
        return match ($this->entity) {
            Invoice::class => 'invoices',
            Quote::class => 'quotes',
            Task::class => 'tasks',
            Credit::class => 'credits',
            RecurringInvoice::class => 'recurring_invoices',
            Project::class => 'projects',
            Expense::class => 'expenses',
            Payment::class => 'payments',
            Product::class => 'products',
            PurchaseOrder::class => 'purchase_orders',
            Project::class => 'projects',
            Client::class => 'clients',
            Vendor::class => 'vendors',
        };
    }

    public function middleware()
    {
        return [new WithoutOverlapping("template-{$this->company->company_key}")];
    }

}



