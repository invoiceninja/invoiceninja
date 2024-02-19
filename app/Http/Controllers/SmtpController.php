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

namespace App\Http\Controllers;

use App\Http\Requests\Smtp\CheckSmtpRequest;
use App\Mail\TestMailServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SmtpController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function check(CheckSmtpRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $user->company();

        config([
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $request->input('smtp_host', $company->smtp_host),
                'port' => $request->input('smtp_port', $company->smtp_port),
                'username' => $request->input('smtp_username', $company->smtp_username),
                'password' => $request->input('smtp_password', $company->smtp_password),
                'encryption' => $request->input('smtp_encryption', $company->smtp_encryption ?? 'tls'),
                'local_domain' => $request->input('smtp_local_domain', strlen($company->smtp_local_domain) > 2 ? $company->smtp_local_domain : null),
                'verify_peer' => $request->input('verify_peer', $company->smtp_verify_peer ?? true),
                'timeout' => 5,
            ],
        ]);
        
        (new \Illuminate\Mail\MailServiceProvider(app()))->register();

        try {
            Mail::to($user->email, $user->present()->name())->send(new TestMailServer('Email Server Works!', strlen($company->settings->custom_sending_email) > 1 ? $company->settings->custom_sending_email : $user->email));
        } catch (\Exception $e) {
            app('mail.manager')->forgetMailers();
            return response()->json(['message' => $e->getMessage()], 400);        
        }

        app('mail.manager')->forgetMailers();

        return response()->json(['message' => 'Ok'], 200);

    }

}