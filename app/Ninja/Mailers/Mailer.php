<?php namespace App\Ninja\Mailers;

use Utils;
use Exception;
use Mail;
use App\Models\Invoice;

/**
 * Class Mailer
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
     * @return bool|string
     */
    public function sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data = [])
    {
        // check the username is set
        if ( ! env('POSTMARK_API_TOKEN') && ! env('MAIL_USERNAME')) {
            return trans('texts.invalid_mail_config');
        }

        // don't send emails to dummy addresses
        if (stristr($toEmail, '@example.com')) {
            return true;
        }

        if (isset($_ENV['POSTMARK_API_TOKEN'])) {
            $views = 'emails.'.$view.'_html';
        } else {
            $views = [
                'emails.'.$view.'_html',
                'emails.'.$view.'_text',
            ];
        }

        try {
            $response = Mail::send($views, $data, function ($message) use ($toEmail, $fromEmail, $fromName, $subject, $data) {

                $toEmail = strtolower($toEmail);
                $replyEmail = $fromEmail;
                $fromEmail = CONTACT_EMAIL;

                $message->to($toEmail)
                        ->from($fromEmail, $fromName)
                        ->replyTo($replyEmail, $fromName)
                        ->subject($subject);

                // Attach the PDF to the email
                if (!empty($data['pdfString']) && !empty($data['pdfFileName'])) {
                    $message->attachData($data['pdfString'], $data['pdfFileName']);
                }

                // Attach documents to the email
                if(!empty($data['documents'])){
                    foreach($data['documents'] as $document){
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

            $invoice->markInvitationSent($invitation, $messageId);
        }

        return true;
    }

    /**
     * @param $exception
     * @return string
     */
    private function handleFailure($exception)
    {
        if (isset($_ENV['POSTMARK_API_TOKEN']) && method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if (! $response) {
                $error = trans('texts.postmark_error', ['link' => link_to('https://status.postmarkapp.com/')]);
                Utils::logError($error);
                return $error;
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
        } elseif ( ! Utils::isNinja()) {
            Utils::logError(Utils::getErrorString($exception));
        }

        return $emailError;
    }
}
