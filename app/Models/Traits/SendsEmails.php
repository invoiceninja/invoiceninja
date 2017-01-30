<?php

namespace App\Models\Traits;

use App\Constants\Domain;
use Utils;

/**
 * Class SendsEmails.
 */
trait SendsEmails
{
    public function getBccEmail()
    {
        return $this->isPro() ? $this->bcc_email : false;
    }

    public function getFromEmail()
    {
        if (! $this->isPro() || ! Utils::isNinja() || Utils::isReseller()) {
            return false;
        }

        return Domain::getEmailFromId($this->domain_id);
    }
}
