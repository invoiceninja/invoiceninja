<?php namespace App\Ninja\Mailers;

use Exception;
use Mail;
use Utils;
use App\Models\Invoice;

class Mailer
{
    public function sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data = [])
    {
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
                if (isset($data['invoiceId'])) {
                    $invoice = Invoice::with('account')->where('id', '=', $data['invoiceId'])->first();
                    if ($invoice->account->pdf_email_attachment && file_exists($invoice->getPDFPath())) {
                        $message->attach(
                            $invoice->getPDFPath(),
                            array('as' => $invoice->getFileName(), 'mime' => 'application/pdf')
                        );
                    }
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
            
            // Track the Postmark message id
            if (isset($_ENV['POSTMARK_API_TOKEN'])) {
                $json = $response->json();
                $invitation->message_id = $json['MessageID'];
            }
            
            $invitation->email_error = null;
            $invitation->sent_date = \Carbon::now()->toDateTimeString();
            $invitation->save();
        }
        
        return true;
    }

    private function handleFailure($exception)
    {
        if (isset($_ENV['POSTMARK_API_TOKEN'])) {
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
