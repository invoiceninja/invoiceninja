<?php

namespace App\Ninja\Mailers;

use App\Constants\Domain;
use App\Models\TicketInvitation;
use Exception;
use Illuminate\Support\Facades\Log;
use Mail;
use Utils;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;
use Postmark\Models\PostmarkAttachment;

/**
 * Class TicketMailer.
 */
class TicketMailer
{
    /**
     * @param $toEmail
     * @param $fromEmail
     * @param $fromName
     * @param $subject
     * @param $view
     * @param array $data
     *
     * @return bool|string
     */
    public function sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data = [])
    {
        // don't send emails to dummy addresses
        if (stristr($toEmail, '@example.com'))
            return true;


        $views = [
            'emails.'.$view.'_html',
            'emails.'.$view.'_text',
        ];

        $toEmail = strtolower($toEmail);
        $replyEmail = $data['replyTo'];

        if (Utils::isSelfHost() && config('app.debug'))
            \Log::info("Sending email - To: {$toEmail} | Reply: {$replyEmail} | From: $fromEmail");


        // Optionally send for alternate domain
        if (! empty($data['fromEmail']))
            $fromEmail = $data['fromEmail'];


        return $this->sendPostmarkMail($toEmail, $fromEmail, $fromName, $replyEmail, $subject, $views, $data);

    }


    private function sendPostmarkMail($toEmail, $fromEmail, $fromName, $replyEmail, $subject, $views, $data = [])
    {

        $htmlBody = view($views[0], $data)->render();

        $textBody = view($views[1], $data)->render();

        $attachments = [];

        if (isset($data['account']))
        {

            $account = $data['account'];

            $logoName = $account->getLogoName();

            if (strpos($htmlBody, 'cid:' . $logoName) !== false && $account->hasLogo())
                $attachments[] = PostmarkAttachment::fromFile($account->getLogoPath(), $logoName, null, 'cid:' . $logoName);

        }

        if (strpos($htmlBody, 'cid:invoiceninja-logo.png') !== false)
        {

            $attachments[] = PostmarkAttachment::fromFile(public_path('images/invoiceninja-logo.png'), 'invoiceninja-logo.png', null, 'cid:invoiceninja-logo.png');
            //$attachments[] = PostmarkAttachment::fromFile(public_path('images/emails/icon-facebook.png'), 'icon-facebook.png', null, 'cid:icon-facebook.png');
            //$attachments[] = PostmarkAttachment::fromFile(public_path('images/emails/icon-twitter.png'), 'icon-twitter.png', null, 'cid:icon-twitter.png');
            //$attachments[] = PostmarkAttachment::fromFile(public_path('images/emails/icon-github.png'), 'icon-github.png', null, 'cid:icon-github.png');

        }

        // Handle invoice attachments
        if (! empty($data['pdfString']) && ! empty($data['pdfFileName']))
            $attachments[] = PostmarkAttachment::fromRawData($data['pdfString'], $data['pdfFileName']);


        if (! empty($data['ublString']) && ! empty($data['ublFileName']))
            $attachments[] = PostmarkAttachment::fromRawData($data['ublString'], $data['ublFileName']);


        if (! empty($data['documents']))
        {

            foreach ($data['documents'] as $document)
                $attachments[] = PostmarkAttachment::fromRawData($document['data'], $document['name']);

        }

        try {

            if (Utils::isNinjaProd())
                $postmarkToken = Domain::getPostmarkTokenFromId($account->domain_id);
            else
                $postmarkToken = config('services.postmark_ticket');

            $client = new PostmarkClient($postmarkToken);

            $message = [
                'To' => $toEmail,
                'From' => sprintf('"%s" <%s>', addslashes($fromName), $fromEmail),
                'ReplyTo' => $replyEmail,
                'Subject' => $subject,
                'TextBody' => $textBody,
                'HtmlBody' => $htmlBody,
                'Attachments' => $attachments,
            ];

            if (! empty($data['bccEmail']))
                $message['Bcc'] = $data['bccEmail'];

            if (! empty($data['tag']))
                $message['Tag'] = $data['tag'];

            $response = $client->sendEmailBatch([$message]);

            if ($messageId = $response[0]->messageid)
                return $this->handleSuccess($data, $messageId);
             else
                return $this->handleFailure($data, $response[0]->message);

        } catch (PostmarkException $exception) {

            return $this->handleFailure($data, $exception->getMessage());

        } catch (Exception $exception) {

            Utils::logError(Utils::getErrorString($exception));

            throw $exception;

        }

    }

    /**
     * @param $response
     * @param $data
     *
     * @return bool
     */
    private function handleSuccess($data, $messageId = false)
    {

        if (isset($data['invitation']))
        {

            $invitation = $data['invitation'];

            if ($invitation)
                $invitation->markSent($messageId);

        }

        return true;
    }

    /**
     * @param $exception
     *
     * @return string
     */
    private function handleFailure($data, $emailError)
    {

        if (isset($data['invitation'])) {

            $invitation = $data['invitation'];

            $invitation->email_error = $emailError;

            $invitation->save();

        } elseif (! Utils::isNinjaProd())
            Utils::logError($emailError);


        return $emailError;

    }
}
