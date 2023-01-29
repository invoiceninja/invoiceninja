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

namespace App\Jobs\Company;

use App\Models\PaymentTerm;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateCompanyPaymentTerms
{
    use MakesHash;
    use Dispatchable;

    protected $company;

    protected $user;

    /**
     * Create a new job instance.
     *
     * @param $company
     * @param $user
     */
    public function __construct($company, $user)
    {
        $this->company = $company;

        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $paymentTerms = [
            ['num_days' => 0, 'name' => 'Net 0', 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['num_days' => 7,  'name'  => '', 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['num_days' => 10, 'name' => '', 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['num_days' => 14, 'name' => '', 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['num_days' => 15, 'name' => '', 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['num_days' => 30, 'name' => '', 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['num_days' => 60, 'name' => '', 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['num_days' => 90, 'name' => '', 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
        ];

        PaymentTerm::insert($paymentTerms);
    }
}
