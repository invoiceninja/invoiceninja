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

    public $support_message;

    public $send_logs;

    public function __construct($support_message, $send_logs)
    {
        $this->support_message = $support_message;
        $this->send_logs = $send_logs;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $system_info = null;
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
            $lines = new LimitIterator($log_file, $last_line - 100, $last_line);

            $log_lines = iterator_to_array($lines);
        }

        $account = auth()->user()->account;

        $priority = '';
        $plan = $account->plan ?: '';

        if(strlen($plan) >1)
            $priority = '[PRIORITY] ';

        $company = auth()->user()->company();
        $user = auth()->user();

        if(Ninja::isHosted())
            $subject = "{$priority}Hosted-{$company->db} :: Customer Support - [{$plan}] ".date('M jS, g:ia');
        else
            $subject = "{$priority}Self Hosted :: Customer Support - [{$plan}] ".date('M jS, g:ia');

        return $this->from(config('mail.from.address'), $user->present()->name()) 
                ->replyTo($user->email, $user->present()->name())
                ->subject($subject)
                ->view('email.support.message', [
                    'support_message' => $this->support_message,
                    'system_info' => $system_info,
                    'laravel_log' => $log_lines,
                    'logo' => $company->present()->logo(),
                ]);
    }
}
