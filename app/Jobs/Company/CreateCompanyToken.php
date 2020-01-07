<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Company;

use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CreateCompanyToken implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $company;

    protected $user;

    protected $custom_token_name;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Company $company, User $user, string $custom_token_name)
    {
        $this->company = $company;
        
        $this->user = $user;

        $this->custom_token_name = $custom_token_name;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : ?CompanyToken
    {
        $this->custom_token_name = $this->custom_token_name ?: $this->user->first_name. ' '. $this->user->last_name;

        $ct = CompanyToken::create([
            'user_id' => $this->user->id,
            'account_id' => $this->company->account->id,
            'token' => Str::random(64),
            'name' => $this->custom_token_name ?: $this->user->first_name. ' '. $this->user->last_name,
            'company_id' => $this->company->id,
        ]);
        
        return $ct;
    }
}
