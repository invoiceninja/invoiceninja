<?php


namespace App\Helpers\Email;


use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use League\CommonMark\CommonMarkConverter;

abstract class EmailBuilder
{
    protected $subject;
    protected $body;
    protected $recipients;
    protected $attachments;
    protected $footer;
    protected $template_style;
    protected $variables = [];
    protected $contact = null;

    private function parseTemplate(string $template_data, bool $is_markdown = true, $contact = null): string
    {
        //process variables
        if (!empty($this->variables)) {
            $data = str_replace(array_keys($this->variables), array_values($this->variables), $template_data);
        }

        //process markdown
        if ($is_markdown) {
            //$data = Parsedown::instance()->line($data);

            $converter = new CommonMarkConverter([
                'html_input' => 'allow',
                'allow_unsafe_links' => true,
            ]);

            $data = $converter->convertToHtml($data);
        }

        return $data;
    }

    /**
     * @param $footer
     * @return $this
     */
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

    /**
     * @param $contact
     * @return $this
     */
    public function setContact($contact)
    {
        $this->contact = $contact;
        return $this;
    }

    /**
     * @param $subject
     * @return $this
     */
    public function setSubject($subject)
    {
        $this->subject = $this->parseTemplate($subject, false, $this->contact);
        return $this;
    }

    /**
     * @param $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->parseTemplate($body, true);
        return $this;
    }

    /**
     * @param $template_style
     * @return $this
     */
    public function setTemplate($template_style)
    {
        $this->template_style = $template_style;
        return $this;
    }


    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return mixed
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * @return mixed
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @return mixed
     */
    public function getFooter()
    {
        return $this->footer;
    }

    /**
     * @return mixed
     */
    public function getTemplate()
    {
        return $this->template_style;
    }
}
