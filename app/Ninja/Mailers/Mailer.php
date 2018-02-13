<?php

namespace App\Ninja\Mailers;

use App\Models\Invoice;
use Exception;
use Mail;
use Utils;

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
            $response = Mail::send($views, $data, function ($message) use ($toEmail, $fromEmail, $fromName, $subject, $data) {
                $toEmail = strtolower($toEmail);
                $replyEmail = $fromEmail;
                $fromEmail = CONTACT_EMAIL;
                //\Log::info("{$toEmail} | {$replyEmail} | $fromEmail");

                // Optionally send for alternate domain
                if (! empty($data['fromEmail'])) {
                    $fromEmail = $data['fromEmail'];
                }

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

            return $this->handleSuccess($response, $data);
        } catch (Exception $exception) {
            return $this->handleFailure($exception);
        }
    }

    /**
     * @param $response
     * @param $data
     *
     * @return bool
     */
    private function handleSuccess($response, $data)
    {
        if (isset($data['invitation'])) {
            $invitation = $data['invitation'];
            $invoice = $invitation->invoice;
            $messageId = false;

            // Track the Postmark message id
            if (isset($_ENV['POSTMARK_API_TOKEN']) && $response) {
                $json = json_decode((string) $response->getBody());
                $messageId = $json->MessageID;
            }

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
    private function handleFailure($exception)
    {
        if (isset($_ENV['POSTMARK_API_TOKEN']) && method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if (! $response) {
                $error = trans('texts.postmark_error', ['link' => link_to('https://status.postmarkapp.com/')]);
                Utils::logError($error);

                if (config('queue.default') === 'sync') {
                    return $error;
                } else {
                    throw $exception;
                }
            }

            $response = $response->getBody()->getContents();
            $response = json_decode($response);
            $emailError = nl2br($response->Message);
        } else {
            $emailError = $exception->getMessage();
        }

        if (isset($data['invitation'])) {
            $invitation = $data['invitation'];
            $invitation->email_error = $emailError;
            $invitation->save();
        } elseif (! Utils::isNinjaProd()) {
            Utils::logError(Utils::getErrorString($exception));
        }

        return $emailError;
    }
}
