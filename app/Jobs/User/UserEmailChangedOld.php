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

namespace App\Jobs\User;

use App\Jobs\Mail\BaseMailerJob;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UserEmailChangedOld extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable;

    protected $email;

    protected $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(string $email, Company $company)
    {
        $this->email = $email;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() 
    {

    }
}
