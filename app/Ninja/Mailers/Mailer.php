<?php namespace App\Ninja\Mailers;

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

        Mail::send($views, $data, function ($message) use ($toEmail, $fromEmail, $fromName, $subject, $data) {
            $replyEmail = $fromEmail;
            $fromEmail = NINJA_FROM_EMAIL;
            
            if(isset($data['invoice_id'])) {
                $invoice = Invoice::with('account')->where('id', '=', $data['invoice_id'])->get()->first();
                if($invoice->account->pdf_email_attachment && file_exists($invoice->getPDFPath())) {
                    $message->attach(
                        $invoice->getPDFPath(),
                        array('as' => $invoice->getFileName(), 'mime' => 'application/pdf')
                    );
                }
            }

            //$message->setEncoder(\Swift_Encoding::get8BitEncoding());
            $message->to($toEmail)->from($fromEmail, $fromName)->replyTo($replyEmail, $fromName)->subject($subject);
        });
    }
}
