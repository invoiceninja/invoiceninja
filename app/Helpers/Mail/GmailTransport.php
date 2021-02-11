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
     * The GMail OAuth Token.
     * @var string token
     */
    protected $token;

    /**
     * Create a new Gmail transport instance.
     *
     * @param Mail $gmail
     * @param string $token
     */
    public function __construct(Mail $gmail, string $token)
    {
        $this->gmail = $gmail;
        $this->token = $token;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        /*We should nest the token in the message and then discard it as needed*/

        $this->beforeSendPerformed($message);

        $this->gmail->using($this->token);
        $this->gmail->to($message->getTo());
        $this->gmail->from($message->getFrom());
        $this->gmail->subject($message->getSubject());
        $this->gmail->message($message->getBody());
        //$this->gmail->message($message->toString());
        $this->gmail->cc($message->getCc());
        $this->gmail->bcc($message->getBcc());

        foreach ($message->getChildren() as $child) {
            nlog("trying to attach");
            nlog($child->getContentType());
            
            if($child->getContentType() != 'text/plain'){

                $this->gmail->attach(TempFile::filePath($child));
                
            }


        } //todo this should 'just work'

        $this->gmail->send();

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }
}
