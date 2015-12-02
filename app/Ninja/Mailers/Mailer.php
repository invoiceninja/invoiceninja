<?php namespace App\Ninja\Mailers;

use Exception;
use Mail;
use Utils;
use App\Models\Invoice;

class Mailer
{
    public function sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data = [])
    {
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
            });

            return $this->handleSuccess($response, $data);
        } catch (Exception $exception) {
            return $this->handleFailure($exception);
        }
    }

    private function handleSuccess($response, $data)
    {
        if (isset($data['invitation'])) {
            $invitation = $data['invitation'];
            $invoice = $invitation->invoice;
            $messageId = false;

            // Track the Postmark message id
            if (isset($_ENV['POSTMARK_API_TOKEN']) && $response) {
                $json = $response->json();
                $messageId = $json['MessageID'];
            }

            $invoice->markInvitationSent($invitation, $messageId);
        }
        
        return true;
    }

    private function handleFailure($exception)
    {
        if (isset($_ENV['POSTMARK_API_TOKEN']) && method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse()->getBody()->getContents();
            $response = json_decode($response);
            $emailError = nl2br($response->Message);
        } else {
            $emailError = $exception->getMessage();
        }

        Utils::logError("Email Error: $emailError");
        
        if (isset($data['invitation'])) {
            $invitation = $data['invitation'];
            $invitation->email_error = $emailError;
            $invitation->save();
        }

        return $emailError;
    }
}
