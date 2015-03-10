<?php namespace ninja\mailers;

use Mail;
use Utils;

class Mailer
{
    public function sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data = [])
    {
        $views = [
            'emails.'.$view.'_html',
            'emails.'.$view.'_text',
        ];

        Mail::send($views, $data, function ($message) use ($toEmail, $fromEmail, $fromName, $subject) {
            $replyEmail = $fromEmail;

            // http://stackoverflow.com/questions/2421234/gmail-appearing-to-ignore-reply-to
            if (Utils::isNinja() && $toEmail != CONTACT_EMAIL) {
                $fromEmail = NINJA_FROM_EMAIL;
            }

            //$message->setEncoder(\Swift_Encoding::get8BitEncoding());
            $message->to($toEmail)->from($fromEmail, $fromName)->replyTo($replyEmail, $fromName)->subject($subject);
        });
    }
}
