<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Mail;

use Microsoft\Graph\Graph;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class Office365MailTransport extends AbstractTransport
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $symfony_message = MessageConverter::toEmail($message->getOriginalMessage()); //@phpstan-ignore-line


        $graph = new Graph();

        /** @phpstan-ignore-next-line **/
        $token = $symfony_message->getHeaders()->get('gmailtoken')->getValue();
        $symfony_message->getHeaders()->remove('gmailtoken');

        $graph->setAccessToken($token);

        $bccs = $symfony_message->getHeaders()->get('Bcc');

        $bcc_list = '';

        if ($bccs) {

            /** @phpstan-ignore-next-line **/
            foreach ($bccs->getAddresses() as $address) {
                $bcc_list .= 'Bcc: "'.$address->getAddress().'" <'.$address->getAddress().'>\r\n';
            }
        }

        try {
            $graphMessage = $graph->createRequest('POST', '/users/'.$symfony_message->getFrom()[0]->getAddress().'/sendmail')
                ->attachBody(base64_encode($bcc_list.$message->toString()))
                ->addHeaders(['Content-Type' => 'text/plain'])
                ->setReturnType(\Microsoft\Graph\Model\Message::class)
                ->execute();
        } catch (\Exception $e) {

            sleep(rand(5, 10));

            try {
                $graphMessage = $graph->createRequest('POST', '/users/'.$symfony_message->getFrom()[0]->getAddress().'/sendmail')
                    ->attachBody(base64_encode($bcc_list.$message->toString()))
                    ->addHeaders(['Content-Type' => 'text/plain'])
                    ->setReturnType(\Microsoft\Graph\Model\Message::class)
                    ->execute();
            } catch (\Exception $e) {

            }

        }
    }

    public function __toString(): string
    {
        return 'office365';
    }
}
