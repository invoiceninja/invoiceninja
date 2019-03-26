<?php

namespace App\Jobs\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateCompanyToken implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $company;

    protected $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Company $company, User $user)
    {
        $this->company = $company;
        
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : void
    {
        $company_token = [
            'user_id' => $this->user->id,
            'account_id' => $company->account->id,
            'token' => str_random(64),
            'name' => $user->first_name. ' '. $user->last_name;
        ];

        $this->company->tokens()->attach($company->id, $company_token);
    }
}
