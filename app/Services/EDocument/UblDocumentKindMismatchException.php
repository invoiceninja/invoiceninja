<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument;

/**
 * Thrown when a supplied UblDocumentKind does not match the Peppol model or decoded UBL type.
 */
class UblDocumentKindMismatchException extends \InvalidArgumentException
{
}
