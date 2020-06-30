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
 * Class DocumentWasDeleted.
 */
class DocumentWasDeleted
{
    use SerializesModels;

    /**
     * @var Document
     */
    public $document;

    public $company;
    /**
     * Create a new event instance.
     *
     * @param Document $document
     */
    public function __construct(Document $document, $company)
    {
        $this->document = $document;
        $this->company = $company;
    }
}
