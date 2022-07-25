<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Mail;

use Illuminate\Support\Str;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\UploadSession;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class Office365MailTransport extends AbstractTransport
{

    public function __construct()
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {

        $symfony_message = MessageConverter::toEmail($message->getOriginalMessage());

        $graph = new Graph();
        $token = $symfony_message->getHeaders()->get('GmailToken')->getValue();
        $symfony_message->getHeaders()->remove('GmailToken');

        $graph->setAccessToken($token);

            try {
                $graphMessage = $graph->createRequest('POST', '/users/'.$symfony_message->getFrom()[0]->getAddress().'/sendmail')
                    ->attachBody(base64_encode($message->toString()))
                    ->addHeaders(['Content-Type' => 'text/plain'])
                    ->setReturnType(\Microsoft\Graph\Model\Message::class)
                    ->execute();
            } catch (\Exception $e) {
                sleep(5);
                $graphMessage = $graph->createRequest('POST', '/users/'.$symfony_message->getFrom()[0]->getAddress().'/sendmail')
                    ->attachBody(base64_encode($message->toString()))
                    ->addHeaders(['Content-Type' => 'text/plain'])
                    ->setReturnType(\Microsoft\Graph\Model\Message::class)
                    ->execute();
            }
        
    }

    public function __toString(): string
    {
        return 'office365';
    }

}
