<?php

namespace App\Mail;

use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use LimitIterator;
use SplFileObject;

class SupportMessageSent extends Mailable
{
    //   use Queueable, SerializesModels;

    public $data;

    public $send_logs;

    public function __construct(array $data, $send_logs)
    {
        $this->data = $data;
        $this->send_logs = $send_logs;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $system_info = request()->has('version') ? 'Version: '.request()->input('version') : 'Version: No Version Supplied.';

        $log_lines = [];

        /*
         * With self-hosted version of Ninja,
         * we are going to bundle system-level info
         * and last 10 lines of laravel.log file.
         */
        if (Ninja::isSelfHost() && $this->send_logs !== false) {
            $system_info = Ninja::getDebugInfo();

            $log_file = new SplFileObject(sprintf('%s/laravel.log', base_path('storage/logs')));

            $log_file->seek(PHP_INT_MAX);
            $last_line = $log_file->key();

            $lines = new LimitIterator($log_file, max(0, $last_line - 100), $last_line);
            $log_lines = iterator_to_array($lines);
        }

        $account = auth()->user()->account;

        $priority = '';
        $plan = $account->plan ?: 'customer support';
        $plan = ucfirst($plan);

        if (strlen($account->plan) > 1) {
            $priority = '[PRIORITY] ';
        }

        $company = auth()->user()->company();
        $user = auth()->user();
        $db = str_replace('db-ninja-', '', $company->db);
        $is_large = $company->is_large ? 'L' : 'S';
        $platform = array_key_exists('platform', $this->data) ? $this->data['platform'] : 'U';
        $migrated = strlen($company->company_key) == 32 ? 'M' : '';
        $trial = $account->isTrial() ? 'T' : '';
        $plan = str_replace('_', ' ', $plan);

        if (Ninja::isHosted()) {
            $subject = "{$priority}Hosted-{$db}-{$is_large}{$platform}{$migrated}{$trial} :: {$plan} :: ".date('M jS, g:ia');
        } else {
            $subject = "{$priority}Self Hosted :: {$plan} :: {$is_large}{$platform}{$migrated} :: ".date('M jS, g:ia');
        }

        return $this->from(config('mail.from.address'), $user->present()->name())
                ->replyTo($user->email, $user->present()->name())
                ->subject($subject)
                ->view('email.support.message', [
                    'support_message' => nl2br($this->data['message']),
                    'system_info' => $system_info,
                    'laravel_log' => $log_lines,
                    'logo' => $company->present()->logo(),
                    'settings' => $company->settings,
                ]);
    }
}
