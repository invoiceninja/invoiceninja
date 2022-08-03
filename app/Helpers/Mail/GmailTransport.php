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

use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Client;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * GmailTransport.
 */
class GmailTransport extends AbstractTransport
{

    public function __construct()
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        nlog("In Do Send");
        $message = MessageConverter::toEmail($message->getOriginalMessage());

        $token = $message->getHeaders()->get('gmailtoken')->getValue();
        $message->getHeaders()->remove('gmailtoken');

        $client = new Client();
        $client->setClientId(config('ninja.auth.google.client_id'));
        $client->setClientSecret(config('ninja.auth.google.client_secret'));
        $client->setAccessToken($token);
        
        $service = new Gmail($client);

        $body = new Message();

        $bccs = $message->getHeaders()->get('Bcc');

        $bcc_list = '';

        if($bccs)
        {
            $bcc_list = 'Bcc: ';

            foreach($bccs->getAddresses() as $address){

                $bcc_list .= $address->getAddress() .',';

            }

            $bcc_list = rtrim($bcc_list, ",") . "\r\n";
        }  

        $body->setRaw($this->base64_encode($bcc_list.$message->toString()));

        $service->users_messages->send('me', $body, []);
        
    }
 
    private function base64_encode($data)
    {
        return rtrim(strtr(base64_encode($data), ['+' => '-', '/' => '_']), '=');
    }

    public function __toString(): string
    {
        return 'gmail';
    }

}
