<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MigrationCompleted extends Mailable
{
    // use Queueable, SerializesModels;

    public $company;

    public $check_data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Company $company, $check_data = '')
    {
        $this->company = $company;
        $this->check_data = $check_data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data['settings'] = $this->company->settings;
        $data['company'] = $this->company->fresh();
        $data['whitelabel'] = $this->company->account->isPaid() ? true : false;
        $data['check_data'] = $this->check_data;

        $result = $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->view('email.import.completed', $data);

        return $result;
    }
}
