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
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $company;

    protected $user;

    protected $custom_token_name;

    /**
     * Create a new job instance.
     *
     * @param Company $company
     * @param User $user
     * @param string $custom_token_name
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
     * @return CompanyToken|null
     */
    public function handle(): ?CompanyToken
    {
        $this->custom_token_name = $this->custom_token_name ?: $this->user->first_name.' '.$this->user->last_name;

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->user->account->id;
        $company_token->name = $this->custom_token_name ?: $this->user->first_name.' '.$this->user->last_name;
        $company_token->token = Str::random(64);
        $company_token->is_system = true;

        $company_token->save();

        return $company_token;
    }
}
