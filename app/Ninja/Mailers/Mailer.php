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

            // http://stackoverflow.com/questions/2421234/gmail-appearing-to-ignore-reply-to
            if (Utils::isNinja() && $toEmail != CONTACT_EMAIL) {
                $fromEmail = NINJA_FROM_EMAIL;
            }
            
            if(isset($data['id'])) {
                $invoice = Invoice::find($data['id']);
                $invoice->load('account');
                $accountAttributes = $invoice->account()->getParent()->getRelations()['account']->getAttributes();
                $pdfPath = storage_path().'/pdfcache/cache-'.$invoice->id.'.pdf';
                
                if($accountAttributes['pdf_email_attachment'] === 1 && file_exists($pdfPath)) {
                    $message->attach(
                        $pdfPath,
                        array('as' => $accountAttributes['name'].'_'.$accountAttributes['invoice_number_prefix'].$invoice->getName().'.pdf', 'mime' => 'application/pdf')
                    );
                }
            }

            //$message->setEncoder(\Swift_Encoding::get8BitEncoding());
            $message->to($toEmail)->from($fromEmail, $fromName)->replyTo($replyEmail, $fromName)->subject($subject);
        });
    }
}
