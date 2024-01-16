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

namespace App\Services\User;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Admin\VerifyUserObject;
use App\Models\Company;
use App\Models\User;
use App\Utils\Ninja;

class UserService
{
    public function __construct(public User $user)
    {
    }

    public function invite(Company $company, bool $is_react = true)
    {

        try {
            $nmo = new NinjaMailerObject();
            $nmo->mailable = new NinjaMailer((new VerifyUserObject($this->user, $company, $is_react))->build());
            $nmo->company = $company;
            $nmo->to_user = $this->user;
            $nmo->settings = $company->settings;

            NinjaMailerJob::dispatch($nmo, true);

            Ninja::registerNinjaUser($this->user);
        } catch (\Exception $e) {
            nlog("I couldn't send the verification email ".$e->getMessage());
        }

        return $this->user;
    }
}
