<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Smtp\CheckSmtpRequest;
use App\Mail\TestMailServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SmtpController extends BaseController
{
    private const SMTP_TIMEOUT = 5;

    public function __construct()
    {
        parent::__construct();
    }

    public function check(CheckSmtpRequest $request)
    {
        $startedAt = microtime(true);

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $user->company();

        $smtp_host = $request->input('smtp_host', $company->smtp_host);
        $smtp_port = (int) $request->input('smtp_port', $company->smtp_port);
        $smtp_username = $request->input('smtp_username', $company->smtp_username);
        $smtp_password = $request->input('smtp_password', $company->smtp_password);
        $smtp_encryption = $request->input('smtp_encryption', $company->smtp_encryption ?? 'tls');
        $smtp_local_domain = $request->input('smtp_local_domain', strlen($company->smtp_local_domain) > 2 ? $company->smtp_local_domain : null);
        $smtp_verify_peer = $request->input('verify_peer', $company->smtp_verify_peer ?? true);

        config([
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $smtp_host,
                'port' => $smtp_port,
                'username' => $smtp_username,
                'password' => $smtp_password,
                'encryption' => $smtp_encryption,
                'local_domain' => $smtp_local_domain,
                'verify_peer' => $smtp_verify_peer,
                'timeout' => self::SMTP_TIMEOUT,
            ],
        ]);

        (new \Illuminate\Mail\MailServiceProvider(app()))->register();

        $failed = false;

        try {

            $sending_email = (isset($company->settings->custom_sending_email) && stripos($company->settings->custom_sending_email, "@")) ? $company->settings->custom_sending_email : $user->email;
            $sending_user = (isset($company->settings->email_from_name) && strlen($company->settings->email_from_name) > 2) ? $company->settings->email_from_name : $user->name();

            $mailable = new TestMailServer('Email Server Works!', $sending_email);
            $mailable->from($sending_email, $sending_user);

            Mail::mailer('smtp')
                ->to($user->email, $user->present()->name())
                ->send($mailable);

        } catch (\Throwable $e) {
            nlog('SMTP check failed', [
                'company_id' => $company->id ?? null,
                'host' => $smtp_host,
                'port' => $smtp_port,
                'error' => $e->getMessage(),
            ]);
            $failed = true;
        }

        app('mail.manager')->forgetMailers();

        $this->padResponse($startedAt);

        if ($failed) {
            return response()->json(['message' => 'Could not connect to SMTP server'], 400);
        }

        return response()->json(['message' => 'Ok'], 200);
    }
    
    /**
     * padResponse
     *
     * provide a constant delay to avoid timing oracles.
     * 
     * @param  float $startedAt
     * @return void
     */
    private function padResponse(float $startedAt): void
    {
        $elapsed = microtime(true) - $startedAt;
        $remaining = self::SMTP_TIMEOUT - $elapsed;

        if ($remaining > 0) {
            usleep((int) ($remaining * 1_000_000));
        }
    }
}
