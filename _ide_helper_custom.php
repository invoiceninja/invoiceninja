<?php

namespace Illuminate\Contracts\Mail
{
    class Mailer
    {
        public function postmark_config(string $key)
        {
            return true;
        }

        public function mailgun_config(string $key)
        {
            return true;
        }

        public function brevo_config(string $key)
        {
            return true;
        }
    }
}
