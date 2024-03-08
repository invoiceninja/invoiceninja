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

namespace App\Jobs\RecurringInvoice;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateRecurring implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public function __construct(public array $ids, public Company $company, public User $user, protected string $action, protected float $percentage = 0)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        MultiDB::setDb($this->company->db);

        $this->user->setCompany($this->company);

        RecurringInvoice::query()->where('company_id', $this->company->id)
            ->whereIn('id', $this->ids)
            ->chunk(100, function ($recurring_invoices) {
                foreach ($recurring_invoices as $recurring_invoice) {
                    if ($this->user->can('edit', $recurring_invoice)) {
                        if ($this->action == 'update_prices') {
                            $recurring_invoice->service()->updatePrice();
                        } elseif ($this->action == 'increase_prices') {
                            $recurring_invoice->service()->increasePrice($this->percentage);
                        }
                    }
                }
            });
    }

    public function failed($exception = null)
    {
    }
}
