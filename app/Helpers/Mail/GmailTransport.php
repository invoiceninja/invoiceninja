<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Mail;

use App\Models\User;
use App\Utils\TempFile;
use Dacastro4\LaravelGmail\Facade\LaravelGmail;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
/**
 * GmailTransport.
 */
class GmailTransport extends AbstractTransport
{
    /**
     * The Gmail instance.
     *
     * @var Mail
     */
    public $gmail;

    public $body;

    /**
     * Create a new Gmail transport instance.
     *
     * @param Mail $gmail
     * @param string $token
     */
    public function __construct()
    {
        parent::__construct();
        $this->gmail = new Mail;
        $this->body = new \Google\Service\Gmail\Message();

    }

    protected function doSend(SentMessage $message): void
    {
        nlog("in Do Send");
        $message = MessageConverter::toEmail($message->getOriginalMessage());

        $token = $message->getHeaders()->get('GmailToken')->getValue();
        // $message->getHeaders()->remove('GmailToken');




        // $this->beforeSendPerformed($message);
        $this->gmail->using($token);
        $this->gmail->to($message->getTo()[0]->getAddress(), $message->getTo()[0]->getName());
        $this->gmail->from($message->getFrom()[0]->getAddress(), $message->getFrom()[0]->getName());


        $this->gmail->subject($message->getSubject());
        $this->gmail->message($message->getHtmlBody());
        $this->gmail->cc($message->getCc());

        if(is_array($message->getBcc()))
            $this->gmail->bcc(array_keys($message->getBcc()));

        foreach ($message->getAttachments() as $child) 
        {

            if($child->getContentType() != 'text/plain')
            {

                $this->gmail->attach(TempFile::filePath($child->getBody(), $child->getName() ));
            
            }

        } 

        $this->gmail->send();
        // $this->gmail->service->users_messages->send('me', $this->body,[]);
        
    }
 
     public function __toString(): string
    {
        return 'gmail';
    }

}
