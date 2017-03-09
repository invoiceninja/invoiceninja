<?php

namespace App\Ninja\Transformers;

use App\Models\Document;

/**
 * @SWG\Definition(definition="Document", @SWG\Xml(name="Document"))
 */
class DocumentTransformer extends EntityTransformer
{
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="name", type="string", example="Test")
     * @SWG\Property(property="type", type="string", example="CSV")
     * @SWG\Property(property="invoice_id", type="integer", example=1)
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
     */
    public function transform(Document $document)
    {
        return array_merge($this->getDefaults($document), [
            'id' => (int) $document->public_id,
            'name' => $document->name,
            'type' => $document->type,
            'invoice_id' => isset($document->invoice->public_id) ? (int) $document->invoice->public_id : null,
            'expense_id' => isset($document->expense->public_id) ? (int) $document->expense->public_id : null,
            'updated_at' => $this->getTimestamp($document->updated_at),
        ]);
    }
}
