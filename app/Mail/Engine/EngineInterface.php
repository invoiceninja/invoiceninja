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

interface EngineInterface
{
    public function setFooter($footer);

    public function setVariables($variables);

    public function setContact($contact);

    public function setSubject($subject);

    public function setBody($body);

    public function setTemplate($template_style);

    public function setAttachments($attachments);

    public function setViewLink($link);

    public function setViewText($text);

    public function getSubject();

    public function getBody();

    public function getAttachments();

    public function getFooter();

    public function getTemplate();

    public function getViewLink();

    public function getViewText();

    public function build();

    public function getTextBody();
}
