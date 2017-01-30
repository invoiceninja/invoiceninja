<?php namespace App\Models\Traits;

use Utils;
use App\Constants\Domain;

/**
 * Class SendsEmails
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
