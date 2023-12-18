<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\IngresEmail;

use Illuminate\Http\UploadedFile;

/**
 * EmailObject.
 */
class IngresEmail
{
    /** @var array[string] $args */
    public array $to = [];

    public string $from;

    public array $reply_to = [];

    /** @var array[string] $args */
    public array $cc = [];

    /** @var array[string] $args */
    public array $bcc = [];

    public ?string $subject = null;

    public ?string $body = null;
    public ?UploadedFile $body_document;

    public string $text_body;

    /** @var array[\Illuminate\Http\UploadedFile] $documents */
    public array $documents = [];

    public ?\DateTimeImmutable $date = null;

    function __constructor()
    {

    }
}
