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

    public $template; //the template to use

    public $message; //the message array (subject and body)

    public $user; //the user the email will be sent from

    public function __construct($message, $template, $user)
    {
        $this->message = $message;
        $this->template = $template;
        $this->user = $user;

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

        //


        return $this->from($this->user->email, $this->user->present()->name()) //todo this needs to be fixed to handle the hosted version
            ->cc()
            ->bcc()
            ->subject($this->message['subject'])
            ->view($template_name, [
                'body' => $this->message['body']
            ]);

    }