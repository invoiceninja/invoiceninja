<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
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

    protected $user_agent;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Company $company, User $user, string $user_agent)
    {
        $this->company = $company;
        
        $this->user = $user;

        $this->user_agent = $user_agent;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : ?CompanyToken
    {

        $ct = CompanyToken::create([
            'user_id' => $this->user->id,
            'account_id' => $this->company->account->id,
            'token' => Str::random(64),
            'name' => $this->user->first_name. ' '. $this->user->last_name,
            'company_id' => $this->company->id,
            'user_agent' => $this->user_agent,
        ]);
        
        return $ct;
    }
}
