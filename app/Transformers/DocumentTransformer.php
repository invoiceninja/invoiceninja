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

namespace App\Transformers;

use App\Models\Document;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class DocumentTransformer extends EntityTransformer
{
    use MakesHash;

    protected $serializer;

    protected array $defaultIncludes = [];

    protected array $availableIncludes = [];

    public function __construct($serializer = null)
    {
        $this->serializer = $serializer;
    }

    public function transform(Document $document)
    {
        return  [
            'id' => $this->encodePrimaryKey($document->id),
            'user_id' => $this->encodePrimaryKey($document->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($document->assigned_user_id),
            'project_id' => $this->encodePrimaryKey($document->project_id),
            'vendor_id' => $this->encodePrimaryKey($document->vendor_id),
            'url' => (string) $document->generateRoute() ?: '',
            'preview' => (string) $document->preview ?: '',
            'name' => (string) $document->name,
            'type' => (string) $document->type,
            'disk' => (string) $document->disk,
            'hash' => (string) $document->hash,
            'size' => (int) $document->size,
            'width' => (int) $document->width,
            'height' => (int) $document->height,
            'is_default' => (bool) $document->is_default,
            'updated_at' => (int) $document->updated_at,
            'archived_at' => (int) $document->deleted_at,
            'created_at' => (int) $document->created_at,
            'is_deleted' => (bool) false,
            'is_public' => (bool) $document->is_public,
            'link' => (string) $document->link(),
            'documentable_id' => $this->encodePrimaryKey($document->documentable_id),
            'documentable_type' => $this->documentableType($document),
        ];
    }

    /**
     * The parent record's type as a snake_case entity name, e.g. invoice,
     * recurring_invoice, client. Morph-mapped types (invoices, proposals) are
     * stored under their alias, so it is resolved to the class first.
     */
    private function documentableType(Document $document): string
    {
        $type = (string) $document->documentable_type;

        return Str::snake(class_basename(Relation::getMorphedModel($type) ?? $type));
    }
}
