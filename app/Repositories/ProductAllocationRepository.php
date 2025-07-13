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

namespace App\Repositories;

use App\Factory\ProductAllocationFactory;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductAllocation;
use App\Utils\Traits\SavesDocuments;
use Exception;
use Illuminate\Support\Carbon;

class ProductAllocationRepository extends BaseRepository
{
    use SavesDocuments;

    /**
     * handles automatic grouping for new upsert data and validates the input further
     * @param array $data
     * @param ProductAllocation $productAllocation
     * @return ProductAllocation|null
     */
    public function store(string $company_id, string $user_id, array $data): ?ProductAllocation
    {
        // VALIDATE INPUT => the system will check if the provided data is valid based on the product configuration
        /** @var Product $product */
        $product;

        $data['client_id'] ??= null;
        $data['project_id'] ??= null;
        $data['invoice_id'] ??= null;
        $data['recurring_id'] ??= null;
        $data['subscription_id'] ??= null;
        $data['quantity'] ??= 0;
        $data['from'] = array_key_exists('from', $data) && isset($data['from']) ? Carbon::parse($data['from']) : null;
        $data['until'] = array_key_exists('until', $data) && isset($data['until']) ? Carbon::parse($data['until']) : null;
        $data['equipment_id'] ??= null;
        $data['invoice_aggregation_key'] ??= null;

        $product = Product::where('id', $data['product_id'])->first();
        if (!isset($product))
            throw new Exception('Invalid product_id.');
        if (!isset($product->allocation_type))
            throw new Exception('Allocation not allowed by product configuration.');

        // GROUP/UPSERT with existing entities => the system will try to check for an existing entry and will merge quantities, if found
        /** @var ProductAllocation $productAllocation */
        $productAllocation;
        if (isset($product->allocation_aggregation_interval)) {
            $query = ProductAllocation::where('invoice_aggregation_key', $data['invoice_aggregation_key'])
                ->where('company_id', $company_id)
                ->where('user_id', $user_id)
                ->where('product_id', $data['product_id'])
                ->where('equipment_id', $data['equipment_id'] ?? null)
                ->where('client_id', $data['client_id'] ?? null)
                ->where('project_id', $data['project_id'] ?? null)
                ->where('invoice_id', $data['invoice_id'] ?? null)
                ->where('recurring_id', $data['recurring_id'] ?? null)
                ->where('subscription_id', $data['subscription_id'] ?? null);

            if ($product->allocation_aggregation_interval == 'hourly') {
                $query = $query->where('created_at', '>=', Carbon::now()->subHour());
            } else if ($product->allocation_aggregation_interval == 'daily') {
                $query = $query->where('created_at', '>=', Carbon::now()->subDay());
            } else if ($product->allocation_aggregation_interval == 'weekly') {
                $query = $query->whereBetween('created_at', [
                    Carbon::now()->startOfWeek(Carbon::MONDAY),
                    Carbon::now()->endOfWeek(Carbon::SUNDAY)
                ]);
            } else if ($product->allocation_aggregation_interval == 'monthly') {
                $query = $query->whereYear('created_at', '=', Carbon::now()->year)
                    ->whereMonth('created_at', '=', Carbon::now()->month);
            } else if ($product->allocation_aggregation_interval == 'yearly') {
                $query = $query->whereYear('created_at', '=', Carbon::now()->year);
            } else if (is_numeric($product->allocation_aggregation_interval)) {
                $query = $query->where('created_at', '>=', Carbon::now()->subSeconds((int) $product->allocation_aggregation_interval)); // Treat numeric value as offset in seconds
            } else if (Carbon::hasFormat(Carbon::now(), $data['invoice_aggregation_key'])) { // For unsupported or custom time frame formats
                $query = $query->whereDate('created_at', '>', Carbon::now()->format($data['invoice_aggregation_key']));
            } else
                throw new Exception('Invalid allocation_aggregation_interval.');

            $productAllocation = $query->orderBy('created_at', 'desc')->first();

            // checks and modifications to input data
            if (isset($productAllocation)) {
                $data['quantity'] += $productAllocation->quantity;
            }
        }

        // create new entry, when we were not able to fetch an existing one
        if (!isset($productAllocation))
            $productAllocation = ProductAllocationFactory::create($company_id, $user_id, $data['product_id']);

        $productAllocation->fill($data);
        $productAllocation->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $productAllocation);
        }

        // apply productAllocation to invoice if specified
        if (isset($data['invoice_id'])) {

            Invoice::find($data['invoice_id'])->service()->applyProductAllocations([$productAllocation])->save();

        }

        return $productAllocation;
    }

    /**
     * @param array $data
     * @param ProductAllocation $productAllocation
     * @return ProductAllocation|null
     */
    public function update(array $data, ProductAllocation $productAllocation): ?ProductAllocation
    {

        $productAllocation->fill($data);
        $productAllocation->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $productAllocation);
        }

        // apply productAllocation to invoice if specified
        if (isset($data['invoice_id'])) {

            Invoice::find($data['invoice_id'])->service()->applyProductAllocations([$productAllocation])->save();

        }

        return $productAllocation;
    }
}
