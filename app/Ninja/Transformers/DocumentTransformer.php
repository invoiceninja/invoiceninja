<?php namespace App\Ninja\Transformers;

use App\Models\Document;

/**
 * Class DocumentTransformer
 */
class DocumentTransformer extends EntityTransformer
{
    /**
     * @param Document $document
     * @return array
     */
    public function transform(Document $document)
    {
        return array_merge($this->getDefaults($document), [
            'id' => (int) $document->public_id,
            'name' => $document->name,
            'type' =>  $document->type,
            'invoice_id' => isset($document->invoice->public_id) ? (int) $document->invoice->public_id : null,
            'expense_id' => isset($document->expense->public_id) ? (int) $document->expense->public_id : null,
            'updated_at' => $this->getTimestamp($document->updated_at),
        ]);
    }
}
