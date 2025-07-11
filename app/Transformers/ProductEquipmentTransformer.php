<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Company;
use App\Models\Document;
use App\Models\ProductEquipment;
use App\Models\User;
use App\Utils\Traits\MakesHash;

class ProductEquipmentTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [
        'documents',
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'company',
        'user',
        'client',
        'project',
        'product',
        'product_allocations',
    ];

    /**
     * @param ProductEquipment $productEquipment
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeUser(ProductEquipment $productEquipment)
    {
        $transformer = new UserTransformer($this->serializer);

        return $this->includeItem($productEquipment->user, $transformer, User::class);
    }

    /**
     * @param ProductEquipment $productEquipment
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeCompany(ProductEquipment $productEquipment)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($productEquipment->company, $transformer, Company::class);
    }

    /**
     * @param ProductEquipment $productEquipment
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeClient(ProductEquipment $productEquipment)
    {
        $transformer = new ProjectTransformer($this->serializer);

        return $this->includeItem($productEquipment->client, $transformer, Product::class);
    }

    /**
     * @param ProductEquipment $productEquipment
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeProject(ProductEquipment $productEquipment)
    {
        $transformer = new ProjectTransformer($this->serializer);

        return $this->includeItem($productEquipment->project, $transformer, Project::class);
    }

    /**
     * @param ProductEquipment $productEquipment
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeProduct(ProductEquipment $productEquipment)
    {
        $transformer = new ProjectTransformer($this->serializer);

        return $this->includeItem($productEquipment->product, $transformer, Product::class);
    }

    /**
     * @param ProductEquipment $productEquipment
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeDocuments(ProductEquipment $productEquipment)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($productEquipment->documents, $transformer, Document::class);
    }

    public function transform(ProductEquipment $productEquipment)
    {
        return [
            'id' => $this->encodePrimaryKey($productEquipment->id),
            'user_id' => $this->encodePrimaryKey($productEquipment->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($productEquipment->assigned_user_id),
            'product_key' => $productEquipment->product->product_key ?: '',
            'client_id' => $productEquipment->client_id ?: '',
            'project_id' => $productEquipment->project_id ?: '',
            'serial_number' => $productEquipment->serial_number ?: '',
            'created_at' => (int) $productEquipment->created_at,
            'updated_at' => (int) $productEquipment->updated_at,
            'archived_at' => (int) $productEquipment->deleted_at,
            'public_notes' => $productEquipment->public_notes ?: '',
            'private_notes' => $productEquipment->private_notes ?: '',
            'custom_value1' => $productEquipment->custom_value1 ?: '',
            'custom_value2' => $productEquipment->custom_value2 ?: '',
            'custom_value3' => $productEquipment->custom_value3 ?: '',
            'custom_value4' => $productEquipment->custom_value4 ?: '',
            'is_deleted' => (bool) $productEquipment->is_deleted,
        ];
    }
}
