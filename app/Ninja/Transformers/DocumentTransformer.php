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
     * @SWG\Property(property="name", type="string", example="sample.png")
     * @SWG\Property(property="type", type="string", example="png")
     * @SWG\Property(property="path", type="string", example="abc/sample.png")
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
            'path' => $document->path,
            'invoice_id' => (int) ($document->invoice_id && $document->invoice ? $document->invoice->public_id : null),
            'expense_id' => (int) ($document->expense_id && $document->expense ? $document->expense->public_id : null),
            'updated_at' => $this->getTimestamp($document->updated_at),
            'created_at' => $this->getTimestamp($document->created_at),
            'is_deleted' => (bool) false,
            'is_default' => (bool) $document->is_default,
            'preview' => $document->preview,
            'size' => (int) $document->size,
            'width' => (int) $document->width,
            'height' => (int) $document->height,
        ]);
    }
}
