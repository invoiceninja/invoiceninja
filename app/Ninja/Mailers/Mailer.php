<?php

namespace App\Ninja\Mailers;

use App\Models\Invoice;
use Exception;
use Mail;
use Utils;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;
use Postmark\Models\PostmarkAttachment;

/**
 * Class Mailer.
 */
class Mailer
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
        if (stristr($toEmail, '@example.com')) {
            return true;
        }

        $views = [
            'emails.'.$view.'_html',
            'emails.'.$view.'_text',
        ];

        $toEmail = strtolower($toEmail);
        $replyEmail = $fromEmail;
        $fromEmail = CONTACT_EMAIL;
        //\Log::info("{$toEmail} | {$replyEmail} | $fromEmail");

        // Optionally send for alternate domain
        if (! empty($data['fromEmail'])) {
            $fromEmail = $data['fromEmail'];
        }

        if (config('services.postmark')) {
            return $this->sendPostmarkMail($toEmail, $fromEmail, $fromName, $replyEmail, $subject, $views, $data);
        } else {
            return $this->sendLaravelMail($toEmail, $fromEmail, $fromName, $replyEmail, $subject, $views, $data);
        }
    }

    private function sendLaravelMail($toEmail, $fromEmail, $fromName, $replyEmail, $subject, $views, $data = [])
    {
        if (Utils::isSelfHost()) {
            if (isset($data['account'])) {
                $account = $data['account'];
                if (env($account->id . '_MAIL_FROM_ADDRESS')) {
                    $fields = [
                        'driver',
                        'host',
                        'port',
                        'from.address',
                        'from.name',
                        'encryption',
                        'username',
                        'password',
                    ];
                    foreach ($fields as $field) {
                        $envKey = strtoupper(str_replace('.', '_', $field));
                        if ($value = env($account->id . '_MAIL_' . $envKey)) {
                            config(['mail.' . $field => $value]);
                        }
                    }
                    (new \Illuminate\Mail\MailServiceProvider(app()))->register();
                }
            }
        }

        try {
            $response = Mail::send($views, $data, function ($message) use ($toEmail, $fromEmail, $fromName, $replyEmail, $subject, $data) {
                $message->to($toEmail)
                        ->from($fromEmail, $fromName)
                        ->replyTo($replyEmail, $fromName)
                        ->subject($subject);

                // Optionally BCC the email
                if (! empty($data['bccEmail'])) {
                    $message->bcc($data['bccEmail']);
                }

                // Handle invoice attachments
                if (! empty($data['pdfString']) && ! empty($data['pdfFileName'])) {
                    $message->attachData($data['pdfString'], $data['pdfFileName']);
                }
                if (! empty($data['ublString']) && ! empty($data['ublFileName'])) {
                    $message->attachData($data['ublString'], $data['ublFileName']);
                }
                if (! empty($data['documents'])) {
                    foreach ($data['documents'] as $document) {
                        $message->attachData($document['data'], $document['name']);
                    }
                }
            });

            return $this->handleSuccess($data);
        } catch (Exception $exception) {
            return $this->handleFailure($data, $exception->getMessage());
        }
    }

    private function sendPostmarkMail($toEmail, $fromEmail, $fromName, $replyEmail, $subject, $views, $data = [])
    {
        $htmlBody = view($views[0], $data)->render();
        $textBody = view($views[1], $data)->render();
        $attachments = [];

        if (isset($data['account'])) {
            $account = $data['account'];
            $logoName = $account->getLogoName();
            $attachments[] = PostmarkAttachment::fromFile($account->getLogoPath(), $logoName, null, 'cid:' . $logoName);
        }

        // Handle invoice attachments
        if (! empty($data['pdfString']) && ! empty($data['pdfFileName'])) {
            $attachments[] = PostmarkAttachment::fromRawData($document['pdfString'], $document['pdfFileName']);
        }
        if (! empty($data['ublString']) && ! empty($data['ublFileName'])) {
            $attachments[] = PostmarkAttachment::fromRawData($document['ublString'], $document['ublFileName']);
        }
        if (! empty($data['documents'])) {
            foreach ($data['documents'] as $document) {
                $attachments[] = PostmarkAttachment::fromRawData($document['data'], $document['name']);
            }
        }

        try {
            $client = new PostmarkClient(config('services.postmark'));
            $message = [
                'To' => $toEmail,
                'From' => $fromEmail,
                'ReplyTo' => $replyEmail,
                'Subject' => $subject,
                'TextBody' => $textBody,
                'HtmlBody' => $htmlBody,
                'Attachments' => $attachments,
            ];

            if (! empty($data['bccEmail'])) {
                $message['Bcc'] = $data['bccEmail'];
            }

            $response = $client->sendEmailBatch([$message]);
            if ($messageId = $response[0]->messageid) {
                return $this->handleSuccess($data, $messageId);
            } else {
                return $this->handleFailure($data, $response[0]->message);
            }
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
        if (isset($data['invitation'])) {
            $invitation = $data['invitation'];
            $invoice = $invitation->invoice;
            $notes = isset($data['notes']) ? $data['notes'] : false;

            if (! empty($data['proposal'])) {
                $invitation->markSent($messageId);
            } else {
                $invoice->markInvitationSent($invitation, $messageId, true, $notes);
            }
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
        } elseif (! Utils::isNinjaProd()) {
            Utils::logError($emailError);
        }

        return $emailError;
    }
}
