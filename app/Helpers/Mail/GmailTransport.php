<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Helpers\Mail;

use App\Utils\TempFile;
use Dacastro4\LaravelGmail\Facade\LaravelGmail;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Illuminate\Mail\Transport\Transport;
use Swift_Mime_SimpleMessage;

/**
 * GmailTransport.
 */
class GmailTransport extends Transport
{
    /**
     * The Gmail instance.
     *
     * @var Mail
     */
    protected $gmail;

    /**
     * Create a new Gmail transport instance.
     *
     * @param Mail $gmail
     * @param string $token
     */
    public function __construct(Mail $gmail)
    {
        $this->gmail = $gmail;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        /*We should nest the token in the message and then discard it as needed*/

        $token = $message->getHeaders()->get('GmailToken')->getValue();
        $user_id = $message->getHeaders()->get('UserId')->getValue();

        LaravelGmail::setUserId($user_id);

        nlog("gmail transporter token = {$token}");
        
        $message->getHeaders()->remove('GmailToken');
        $message->getHeaders()->remove('UserId');

        nlog("inside gmail sender with token {$token}");

        $this->beforeSendPerformed($message);

        $this->gmail->using($token);
        $this->gmail->to($message->getTo());
        $this->gmail->from($message->getFrom());
        $this->gmail->subject($message->getSubject());
        $this->gmail->message($message->getBody());
        //$this->gmail->message($message->toString());
        $this->gmail->cc($message->getCc());
        $this->gmail->bcc($message->getBcc());

        foreach ($message->getChildren() as $child) 
        {

            nlog("trying to attach");

            if($child->getContentType() != 'text/plain')
            {

            $this->gmail->attach(TempFile::filePath($child->getBody(), $child->getHeaders()->get('Content-Type')->getParameter('name') ));
            
            }


        } 


        $this->gmail->send();

        $this->sendPerformed($message);


        return $this->numberOfRecipients($message);
    }
}
