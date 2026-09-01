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

namespace App\Http\Requests\Document;

use App\Http\Requests\Request;
use App\Models\Document;
use App\Models\VendorContact;
use App\Utils\Traits\MakesHash;

class DownloadMultipleDocumentsRequest extends Request
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\VendorContact $contact */
        $contact = auth()->guard('vendor')->user();

        $document_ids = $this->transformKeys($this->file_hash ?? []);

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents */
        $documents = Document::query()
            ->with('documentable')
            ->whereIn('id', $document_ids)
            ->where('company_id', $contact->company_id)
            ->get();

        // Fail if any requested document doesn't exist in this company
        if ($documents->count() !== count($document_ids)) {
            return false;
        }

        foreach ($documents as $document) {
            if (! $this->contactCanAccessDocument($contact, $document)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'file_hash' => ['required', 'array'],
        ];
    }

    private function contactCanAccessDocument(VendorContact $contact, Document $document): bool
    {
        if (! $document->is_public) {
            return false;
        }

        // Public company-level documents
        if ($document->documentable_type == 'App\Models\Company') {
            return $document->company_id == $contact->company_id;
        }

        // Documents attached directly to a client
        if ($document->documentable_type == 'App\Models\Vendor') {
            return VendorContact::where('vendor_id', $document->documentable_id)
                                ->where('email', $contact->email)
                                ->where('company_id', $contact->company_id)
                                ->exists();
        }

        $entity = $document->documentable;

        if ($entity === null || ! isset($entity->vendor_id)) {
            return false;
        }

        // Public documents on entities (Invoice, Quote, etc.) belonging to a client
        // this contact has access to
        return VendorContact::where('vendor_id', $entity->vendor_id)
                            ->where('email', $contact->email)
                            ->where('company_id', $contact->company_id)
                            ->exists();
    }

}
