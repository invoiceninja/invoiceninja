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
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Utils\Traits\SavesDocuments;
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
    public function save(string $company_id, string $user_id, array $data): ?ProductAllocation
    {
        // VALIDATE INPUT => the system will check if the provided data is valid based on the product configuration
        /** @var Product $product */
        $product;

        $data['serial_number'] ??= null;
        $data['client_id'] ??= null;
        $data['project_id'] ??= null;
        $data['invoice_id'] ??= null;
        $data['recurring_id'] ??= null;
        $data['subscription_id'] ??= null;
        $data['quantity'] ??= null;
        $data['from'] = array_key_exists('from', $data) ? Carbon::parse($data['from']) : null;
        $data['until'] = array_key_exists('until', $data) ? Carbon::parse($data['until']) : null;
        $data['invoice_aggregation_key'] ??= null;

        if (array_key_exists('product_key', $data) && is_string($data['product_key'])) {
            $product = Product::where('product_key', $data['product_key'])->first('id');
            $data['product_id'] = $product->id;
        } else
            throw new \Exception('Missing product_key.');

        if ($product->allocation_type === null)
            throw new \Exception('Allocation not allowed.');

        // serial_number
        if ($product->serial_number_required === true && !isset($data['serial_number']))
            throw new \Exception('Missing serial_number.');
        if ($product->serial_number_required === false && isset($data['serial_number']))
            throw new \Exception('Not Allowed serial_number.');
        if (array_key_exists('serial_number', $data) && $data['quantity'] !== 1)
            throw new \Exception('Invalid quantity. serial_number requires quantity to be 1.');

        // quantity validity
        if ($product->allocation_type === Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED && isset($data['quantity']))
            throw new \Exception('Invalid quantity. Quantity is computed automaticly.');
        if (isset($data['quantity']) && $data['quantity'] <= 0)
            throw new \Exception('Invalid quantity. 0 not allowed.');
        if (isset($product->allocation_max_quantity) && $data['quantity'] <= $product->allocation_max_quantity)
            throw new \Exception('Invalid quantity. allocation_max_quantity exceeded.');

        // from/until validity
        if ($data['from'] !== null && $data['until'] !== null && $data['from']->gt($data['until']))
            throw new \Exception('Invalid from/until.');
        if ($product->allocation_type === Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED && !isset($data['from']))
            throw new \Exception('Invalid from/until. From required for time based allocations.');

        // PRODUCT_ALLOCATION_TYPE_TIME_BASED => validate/calculate quantity
        if ($product->allocation_type === Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED && isset($data['from']) && isset($data['until'])) {
            if ($product->unit_of_measure === 'M')
                $data['quantity'] = $data['from']->diffInMinutes($data['until']);
            else if ($product->unit_of_measure === 'H')
                $data['quantity'] = $data['from']->diffInHours($data['until']);
            else if ($product->unit_of_measure === 'D')
                $data['quantity'] = $data['from']->diffInDays($data['until']);
        }

        // subscription/recurring/project validation
        if (isset($data['subscription_id'])) {
            $subscription = Subscription::where('id', $data['subscription_id'])->first();
            if (empty($subscription))
                throw new \Exception('Subscription not found.');

            if (!empty($subscription->client_id) && $subscription->client_id != $data['client_id'])
                throw new \Exception('Subscription does not belong to client.');
            if (!empty($subscription->project_id) && $subscription->project_id != $data['project_id'])
                throw new \Exception('Subscription does not belong to project.');
            if (!empty($subscription->recurring_id) && $subscription->recurring_id != $data['recurring_id'])
                throw new \Exception('Subscription does not belong to project.');
            $data['client_id'] = $subscription->client_id;
            $data['project_id'] = $subscription->project_id;
            $data['recurring_id'] = $subscription->recurring_id;
        } else if (isset($data['recurring_id'])) {
            $recurring = RecurringInvoice::where('id', $data['recurring_id'])->first();
            if (empty($recurring))
                throw new \Exception('RecurringInvoice not found.');

            if (!empty($recurring->client_id) && $recurring->client_id != $data['client_id'])
                throw new \Exception('RecurringInvoice does not belong to client.');
            if (!empty($recurring->project_id) && $recurring->project_id != $data['project_id'])
                throw new \Exception('RecurringInvoice does not belong to project.');
            $data['client_id'] = $recurring->client_id;
            $data['project_id'] = $recurring->project_id;
        } else if (isset($data['project_id'])) {
            $project = Project::where('id', $data['project_id'])->first();
            if (empty($project))
                throw new \Exception('Project not found.');

            if (!empty($project->client_id) && $project->client_id != $data['client_id'])
                throw new \Exception('Project does not belong to client.');
            $data['client_id'] = $project->client_id;
        }

        // invoice validation
        if (isset($data['invoice_id'])) {
            $invoice = Invoice::where('id', $data['invoice_id'])->first();
            if (empty($invoice))
                throw new \Exception('Invoice not found.');

            if (!empty($invoice->client_id) && $invoice->client_id != $data['client_id'])
                throw new \Exception('Invoice does not belong to client.');
            if (!empty($invoice->project_id) && $invoice->project_id != $data['project_id'])
                throw new \Exception('Invoice does not belong to project.');

            if ($invoice->status != Invoice::STATUS_DRAFT)
                throw new \Exception('Invoice already sent.');

            $data['client_id'] = $invoice->client_id;
            $data['project_id'] = $invoice->project_id;
        }

        // serial_number company wide uniqueness check
        if (isset($data['serial_number'])) {
            $existingQuery = ProductAllocation::where('company_id', $company_id)
                ->where('serial_number', $data['serial_number']);

            if ($data['from'] !== null && $data['until'] !== null) {
                // Standard overlap condition
                $existingQuery->where('from', '<=', $data['until'])
                    ->where('until', '>=', $data['from']);
            } elseif ($data['from'] !== null && $data['until'] === null) {
                // Open-ended range, match anything that starts before or at $data['from'] and has no end or ends after
                $existingQuery->where(function ($q) use ($data) {
                    $q->where('until', '>=', $data['from'])->orWhereNull('until');
                });
            } elseif ($data['from'] === null && $data['until'] !== null) {
                // Any allocation that starts before until
                $existingQuery->where('from', '<=', $data['until']);
            } else {
                $existingQuery->where('from', null)->where('until', null);
            }

            if ($existingQuery->exists())
                throw new \Exception('Duplicate detected.');
        }


        // GROUP/UPSERT with existing entities => the system will try to check for an existing entry and will merge quantities, if found
        /** @var ProductAllocation $productAllocation */
        $productAllocation;

        // aggregation of entries for products, which are likly not equipments
        if (isset($data['invoice_aggregation_key']) && !isset($data['serial_number'])) {

            // find unique entry
            $query = ProductAllocation::where('invoice_aggregation_key', $data['invoice_aggregation_key'])
                ->where('company_id', $company_id)
                ->where('user_id', $user_id)
                ->where('product_id', $data['product_id'])
                ->where('serial_number', $data['serial_number'] ?? null)
                ->where('client_id', $data['client_id'] ?? null)
                ->where('project_id', $data['project_id'] ?? null)
                ->where('invoice_id', $data['invoice_id'] ?? null)
                ->where('recurring_id', $data['recurring_id'] ?? null)
                ->where('subscription_id', $data['subscription_id'] ?? null);

            // custom queries for aggregationKey useable with custom time frames
            if (isset($product->allocation_aggregation_interval)) {

                if ($product->allocation_aggregation_interval === 'hourly') {
                    $query = $query->where('created_at', '>=', Carbon::now()->subHour());
                } else if ($product->allocation_aggregation_interval === 'daily') {
                    $query = $query->where('created_at', '>=', Carbon::now()->subDay());
                } else if ($product->allocation_aggregation_interval === 'weekly') {
                    $query = $query->whereBetween('created_at', [
                        Carbon::now()->startOfWeek(Carbon::MONDAY),
                        Carbon::now()->endOfWeek(Carbon::SUNDAY)
                    ]);
                } else if ($product->allocation_aggregation_interval === 'monthly') {
                    $query = $query->whereYear('created_at', '=', Carbon::now()->year)
                        ->whereMonth('created_at', '=', Carbon::now()->month);
                } else if ($product->allocation_aggregation_interval === 'yearly') {
                    $query = $query->whereYear('created_at', '=', Carbon::now()->year);
                } else if (is_numeric($product->allocation_aggregation_interval)) {
                    $query = $query->where('created_at', '>=', Carbon::now()->subSeconds((int) $product->allocation_aggregation_interval)); // Treat numeric value as offset in seconds
                } else if (Carbon::hasFormat(Carbon::now(), $data['invoice_aggregation_key'])) { // For unsupported or custom time frame formats
                    $query = $query->whereDate('created_at', '>', Carbon::now()->format($data['invoice_aggregation_key']));
                } else
                    throw new \Exception('Invalid allocation_aggregation_interval.');

            }

            // fetch data
            $productAllocation = $query->orderBy('created_at', 'desc')->first();

            // checks and modifications to input data
            if (isset($productAllocation)) {
                // ignore when merging would result in an invalid entry
                if (isset($product->allocation_max_quantity) && $data['quantity'] + $productAllocation->quantity > $product->allocation_max_quantity)
                    $productAllocation = null;
                // increment quantity to carry over existing quantity
                else
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

        // TODO: dispatch job for reloading invoice, when invoice is provided

        return $productAllocation;
    }
}
