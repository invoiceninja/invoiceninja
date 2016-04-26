<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\Document;
use League\Fractal;

class DocumentTransformer extends EntityTransformer
{
    public function transform(Document $document)
    {

        return [
            'id' => (int) $document->public_id,
            'name' => $document->name,
            'account_key' => $this->account->account_key,
            'type' =>  $document->type,
            'invoice_id' => isset($document->invoice->public_id) ? (int) $document->invoice->public_id : null,
            'expense_id' => isset($document->expense->public_id) ? (int) $document->expense->public_id : null,
        ];
    }
}