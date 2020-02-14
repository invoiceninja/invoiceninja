<?php
namespace App\Mail;

use App\Helpers\Email\BuildEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TemplateEmail extends Mailable
{
    use Queueable, SerializesModels;
    private $build_email; //the message array  // ['body', 'footer', 'title', 'files']
    private $user; //the user the email will be sent from
    private $customer;

    public function __construct(BuildEmail $build_email, $user, $customer)
    {
        $this->build_email = $build_email;
        $this->user = $user; //this is inappropriate here, need to refactor 'user' in this context the 'user' could also be the 'system'
        $this->customer = $customer;
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
        $template_name = 'email.template.' . $this->build_email->getTemplate();

        $settings = $this->customer->getMergedSettings();
        \Log::error(print_r($settings, 1));
        $company = $this->customer->account;

        $message = $this->from($this->user->email,
            $this->user->present()->name())//todo this needs to be fixed to handle the hosted version
        ->subject($this->message['subject'])
            ->text('email.template.plain', ['body' => $this->message['body'], 'footer' => $this->message['footer']])
            ->view($template_name, [
                'body' => $this->build_email->getBody(),
                'footer' => $this->build_email->getFooter(),
                'title' => $this->build_email->getSubject(),
                'settings' => $settings,
                'company' => $company
            ]);

         //conditionally attach files
         if($settings->pdf_email_attachment !== false && !empty($this->build_email->getAttachments())){

             foreach($this->build_email->getAttachments() as $file)
                 $message->attach($file);
         }

         return $message;
    }
}
