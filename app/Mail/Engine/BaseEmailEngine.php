<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Engine;

class BaseEmailEngine implements EngineInterface
{
    public $footer;

    public $variables;

    public $contact;

    public $subject;

    public $body;

    public $template_style;

    public $attachments = [];

    public $link;

    public $text;

    public $invitation;

    public $text_body;

    public $text_footer;

    public function setFooter($footer)
    {
        $this->footer = $footer;

        return $this;
    }

    public function setVariables($variables)
    {
        $this->variables = $variables;

        return $this;
    }

    public function setContact($contact)
    {
        $this->contact = $contact;

        return $this;
    }

    public function setSubject($subject)
    {
        if (! empty($this->variables)) {
            $subject = str_replace(array_keys($this->variables), array_values($this->variables), $subject);
        }

        $this->subject = $subject;

        return $this;
    }

    public function setBody($body)
    {
        if (! empty($this->variables)) {
            $body = str_replace(array_keys($this->variables), array_values($this->variables), $body);
            $body = str_replace(array_keys($this->variables), array_values($this->variables), $body);
        }

        $this->body = $body;

        return $this;
    }

    public function setTemplate($template_style)
    {
        $this->template_style = $template_style;

        return $this;
    }

    public function setAttachments($attachments)
    {
        $this->attachments = array_merge($this->getAttachments(), $attachments);

        return $this;
    }

    public function setViewLink($link)
    {
        $this->link = $link;

        return $this;
    }

    public function setViewText($text)
    {
        $this->text = $text;

        return $this;
    }

    public function setTextBody($text)
    {
        $this->text_body = $text;

        return $this;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getAttachments()
    {
        return $this->attachments;
    }

    public function getFooter()
    {
        return $this->footer;
    }

    public function getTemplate()
    {
        return $this->template_style;
    }

    public function getViewLink()
    {
        return $this->link;
    }

    public function getViewText()
    {
        return $this->text;
    }

    public function build()
    {
    }

    public function setInvitation($invitation)
    {
        $this->invitation = $invitation;

        return $this;
    }

    public function getInvitation()
    {
        return $this->invitation;
    }

    public function getTextBody()
    {
        return $this->text_body;
    }

    private function replaceEntities($content)
    {
        $find = [
            '<p>',
            '</p>',
            '<div class="center">',
            '<\div>',
        ];

        $replace = [
            '',
            '\n\n',
            '',
            '\n\n',
        ];

        return str_replace($find, $replace, $content);
    }
}
