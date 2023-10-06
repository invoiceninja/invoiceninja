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
     * @param Builder | Invoice | Quote | Task | Credit | RecurringInvoice | Project | Expense | Client | Payment | Product | PurchaseOrder | Vendor $entity The entity class name
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
        MultiDB::setDb($this->db);

        $key = $this->resolveEntityString();

        $entity = new $this->entity();

        $template = Design::withTrashed()->find($this->decodePrimaryKey($this->template));

        $template_service = new TemplateService($template);
        
        $resource = $entity->query()
               ->withTrashed()
               ->whereIn('id', $this->transformKeys($this->ids))
               ->where('company_id', $this->company->id);

               if($this->entity == Invoice::class)
                    $resource->with('payments','client');

        $resource->get();

        if(count($resource) <= 1)
            $data[$key] = [$resource];
        else 
            $data[$key] = $resource;

        $pdf = $template_service->build($data)->getPdf();

        if($this->send_email)
            $this->sendEmail($pdf);
        else {

            $filename = "templates/{$this->hash}.pdf";
            Storage::disk(config('filesystems.default'))->put($filename, $pdf);

        }
    }

    private function sendEmail(mixed $pdf): mixed
    {
        //send the email.
        return $pdf;
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



