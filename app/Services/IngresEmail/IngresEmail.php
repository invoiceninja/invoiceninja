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
use Illuminate\Support\Carbon;

/**
 * EmailObject.
 */
class IngresEmail
{
    public string $to;

    public string $from;

    public ?string $subject = null;

    public ?string $body = null;
    public ?UploadedFile $body_document = null;

    public string $text_body;

    /** @var array[\Illuminate\Http\UploadedFile] $documents */
    public array $documents = [];

    public ?Carbon $date = null;

    function __constructor()
    {

    }
}
