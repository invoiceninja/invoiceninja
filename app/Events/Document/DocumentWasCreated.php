<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Events\Document;

use App\Models\Document;
use Illuminate\Queue\SerializesModels;

/**
 * Class DocumentWasCreated.
 */
class DocumentWasCreated
{
    use SerializesModels;

    /**
     * @var Document
     */
    public $document;

    /**
     * Create a new event instance.
     *
     * @param Document $document
     */
    public function __construct(Document $document)
    {
        $this->document = $document;
    }
}
