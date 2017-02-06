<?php

namespace App\Models\Traits;

use App\Constants\Domain;
use Utils;

/**
 * Class SendsEmails.
 */
trait SendsEmails
{
    /**
     * @param $entityType
     *
     * @return mixed
     */
    public function getDefaultEmailSubject($entityType)
    {
        if (strpos($entityType, 'reminder') !== false) {
            $entityType = 'reminder';
        }

        return trans("texts.{$entityType}_subject", ['invoice' => '$invoice', 'account' => '$account']);
    }

    /**
     * @param $entityType
     *
     * @return mixed
     */
    public function getEmailSubject($entityType)
    {
        if ($this->hasFeature(FEATURE_CUSTOM_EMAILS)) {
            $field = "email_subject_{$entityType}";
            $value = $this->$field;

            if ($value) {
                return $value;
            }
        }

        return $this->getDefaultEmailSubject($entityType);
    }

    /**
     * @param $entityType
     * @param bool $message
     *
     * @return string
     */
    public function getDefaultEmailTemplate($entityType, $message = false)
    {
        if (strpos($entityType, 'reminder') !== false) {
            $entityType = ENTITY_INVOICE;
        }

        $template = '<div>$client,</div><br>';

        if ($this->hasFeature(FEATURE_CUSTOM_EMAILS) && $this->email_design_id != EMAIL_DESIGN_PLAIN) {
            $template .= '<div>' . trans("texts.{$entityType}_message_button", ['amount' => '$amount']) . '</div><br>' .
                         '<div style="text-align: center;">$viewButton</div><br>';
        } else {
            $template .= '<div>' . trans("texts.{$entityType}_message", ['amount' => '$amount']) . '</div><br>' .
                         '<div>$viewLink</div><br>';
        }

        if ($message) {
            $template .= "$message<p/>\r\n\r\n";
        }

        return $template . '$footer';
    }

    /**
     * @param $entityType
     * @param bool $message
     *
     * @return mixed
     */
    public function getEmailTemplate($entityType, $message = false)
    {
        $template = false;

        if ($this->hasFeature(FEATURE_CUSTOM_EMAILS)) {
            $field = "email_template_{$entityType}";
            $template = $this->$field;
        }

        if (! $template) {
            $template = $this->getDefaultEmailTemplate($entityType, $message);
        }

        // <br/> is causing page breaks with the email designs
        return str_replace('/>', ' />', $template);
    }

    /**
     * @param string $view
     *
     * @return string
     */
    public function getTemplateView($view = '')
    {
        return $this->getEmailDesignId() == EMAIL_DESIGN_PLAIN ? $view : 'design' . $this->getEmailDesignId();
    }

    /**
     * @return mixed|string
     */
    public function getEmailFooter()
    {
        if ($this->email_footer) {
            // Add line breaks if HTML isn't already being used
            return strip_tags($this->email_footer) == $this->email_footer ? nl2br($this->email_footer) : $this->email_footer;
        } else {
            return '<p><div>' . trans('texts.email_signature') . "\n<br>\$account</div></p>";
        }
    }

    /**
     * @param $reminder
     *
     * @return bool
     */
    public function getReminderDate($reminder)
    {
        if (! $this->{"enable_reminder{$reminder}"}) {
            return false;
        }

        $numDays = $this->{"num_days_reminder{$reminder}"};
        $plusMinus = $this->{"direction_reminder{$reminder}"} == REMINDER_DIRECTION_AFTER ? '-' : '+';

        return date('Y-m-d', strtotime("$plusMinus $numDays days"));
    }

    /**
     * @param Invoice $invoice
     *
     * @return bool|string
     */
    public function getInvoiceReminder(Invoice $invoice)
    {
        for ($i = 1; $i <= 3; $i++) {
            if ($date = $this->getReminderDate($i)) {
                $field = $this->{"field_reminder{$i}"} == REMINDER_FIELD_DUE_DATE ? 'due_date' : 'invoice_date';
                if ($invoice->$field == $date) {
                    return "reminder{$i}";
                }
            }
        }

        return false;
    }

    public function setTemplateDefaults($type, $subject, $body)
    {
        if ($subject) {
            $this->{"email_subject_" . $type} = $subject;
        }

        if ($body) {
            $this->{"email_template_" . $type} = $body;
        }

        $this->save();
    }

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
