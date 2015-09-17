<?php namespace App\Ninja\Mailers;

use Exception;
use Mail;
use Utils;
use App\Models\Invoice;

class Mailer
{
    public function sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data = [])
    {
        $views = [
            'emails.'.$view.'_html',
            'emails.'.$view.'_text',
        ];

        try {
            Mail::send($views, $data, function ($message) use ($toEmail, $fromEmail, $fromName, $subject, $data) {

                $toEmail = strtolower($toEmail);
                $replyEmail = $fromEmail;
                $fromEmail = CONTACT_EMAIL;

                $message->to($toEmail)
                        ->from($fromEmail, $fromName)
                        ->replyTo($replyEmail, $fromName)
                        ->subject($subject);

                if (isset($data['invoice_id'])) {
                    $invoice = Invoice::with('account')->where('id', '=', $data['invoice_id'])->first();
                    if ($invoice->account->pdf_email_attachment && file_exists($invoice->getPDFPath())) {
                        $message->attach(
                            $invoice->getPDFPath(),
                            array('as' => $invoice->getFileName(), 'mime' => 'application/pdf')
                        );
                    }
                }
            });
            
            return true;
        } catch (Exception $exception) {
            Utils::logError('Email Error: ' . $exception->getMessage());
            if (isset($_ENV['POSTMARK_API_TOKEN'])) {
                $response = $exception->getResponse()->getBody()->getContents();
                $response = json_decode($response);
                return nl2br($response->Message);
            } else {
                return $exception->getMessage();
            }
        }
    }
}
