<?php

namespace App\Mail;

use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent extends Mailable
{
    use Queueable, SerializesModels;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $system_info = Ninja::getDebugInfo();

        $log_lines = null;
        $log_file = new \SplFileObject(sprintf('%s/laravel.log', base_path('storage/logs')));

        $log_file->seek(PHP_INT_MAX);
        $last_line = $log_file->key();
        $lines = new \LimitIterator($log_file, $last_line - 10, $last_line);

        $log_lines = iterator_to_array($lines);

        return $this->from(config('mail.from.address'))
            ->subject(ctrans('texts.new_support_message'))
            ->markdown('email.support.message', [
                'message' => $this->message,
                'system_info' => Ninja::getDebugInfo(),
                'laravel_log' => $log_lines ?? null
            ]);
    }
}

