<?php

namespace App\Mail;

use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TemplateEmail extends Mailable
{
    use Queueable, SerializesModels;

    private $template; //the template to use

    private $message; //the message array  // ['body', 'footer', 'title', 'files']

    private $user; //the user the email will be sent from

    private $client;


    public function __construct($message, $template, $user, $client)
    {
        $this->message = $message;
        $this->template = $template;
        $this->user = $user; //this is inappropriate here, need to refactor 'user' in this context the 'user' could also be the 'system'
        $this->client = $client;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        /*Alter Run Time Mailer configuration (driver etc etc) to regenerate the Mailer Singleton*/

        //if using a system level template
        $template_name = 'email.template.'.$this->template;

        $settings = $this->client->getMergedSettings();

        $company = $this->client->company;

        $message = $this->from($this->user->email, $this->user->present()->name()) //todo this needs to be fixed to handle the hosted version
            ->subject($this->message['subject'])
            ->text('email.template.plain', ['body' => $this->message['body'], 'footer' => $this->message['footer']])
            ->view($template_name, [
                'body' => $this->message['body'],
                'footer' => $this->message['footer'],
                'title' => $this->message['title'],
                'settings' => $settings,
                'company' => $company
            ]);


        //conditionally attach files
        if ($settings->pdf_email_attachment !== false && array_key_exists('files', $this->message)) {
            foreach ($this->message['files'] as $file) {
                $message->attach($file);
            }
        }

        return $message;
    }
}
