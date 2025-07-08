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

use App\Models\User;
use App\Models\Quote;
use App\Models\Backup;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\Activity;
use App\Utils\HtmlEngine;
use App\Models\CompanyToken;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use App\Utils\VendorHtmlEngine;
use App\Models\RecurringInvoice;
use App\Services\Pdf\PdfService;
use App\Utils\Traits\MakesInvoiceHtml;

/**
 * Class for activity repository.
 */
class ActivityRepository extends BaseRepository
{
    use MakesInvoiceHtml;
    use MakesHash;

    /**
     * Save the Activity.
     *
     * @param \stdClass $fields The fields
     * @param \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder | \App\Models\Expense | \App\Models\Payment $entity
     * @param array $event_vars
     */
    public function save($fields, $entity, $event_vars)
    {
        $activity = new Activity();

        foreach ($fields as $key => $value) {
            $activity->{$key} = $value;
        }

        if ($entity->company) {
            $activity->account_id = $entity->company->account_id;
        }

        if ($token_id = $this->getTokenId($event_vars)) {
            $activity->token_id = $token_id;
        }

        $activity->ip = $event_vars['ip'] ?: ' ';
        $activity->is_system = $event_vars['is_system'];

        $activity->save();

        //rate limiter
        if (!in_array($fields->activity_type_id, [Activity::EMAIL_INVOICE, Activity::EMAIL_CREDIT, Activity::EMAIL_QUOTE, Activity::EMAIL_PURCHASE_ORDER])) {
            $this->createBackup($entity, $activity);
        }
    }

    /**
     * Creates a backup.
     *
     * @param \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder | \App\Models\Expense $entity
     * @param \App\Models\Activity $activity  The activity
     */
    public function createBackup($entity, $activity)
    {
        if ($entity instanceof User || $entity->company->is_disabled || $entity->company?->account->isFreeHostedClient()) {
            return;
        }

        $entity = $entity->fresh();

        if (get_class($entity) == Invoice::class
            || get_class($entity) == Quote::class
            || get_class($entity) == Credit::class
            || get_class($entity) == RecurringInvoice::class
        ) {
            $backup = new Backup();
            $entity->load('client');
            $backup->amount = $entity->amount;
            $backup->activity_id = $activity->id;
            $backup->json_backup = '';
            $backup->save();

            $backup->storeRemotely($this->generateHtml($entity), $entity->client);

            return;
        }

        if (get_class($entity) == PurchaseOrder::class) {

            $backup = new Backup();
            $entity->load('client');
            $backup->amount = $entity->amount;
            $backup->activity_id = $activity->id;
            $backup->json_backup = '';
            $backup->save();

            $backup->storeRemotely($this->generateHtml($entity), $entity->vendor);

            return;

        }
    }

    public function getTokenId(array $event_vars)
    {
        if (isset($event_vars['token']) &&$event_vars['token']) {
            /** @var \App\Models\CompanyToken $company_token **/
            $company_token = CompanyToken::query()->where('token', $event_vars['token'])->first();

            if ($company_token) {
                return $company_token->id;
            }
        }

        return false;
    }

    private function generateHtml($entity)
    {
        $entity_design_id = '';
        $entity_type = '';
        $document_type = 'product';

        $settings = $entity->client ? $entity->client->getMergedSettings() : $entity->vendor->getMergedSettings();

        if ($entity instanceof Invoice) {
            $entity_type = 'invoice';
            $entity_design_id = 'invoice_design_id';
            $entity->load('client.company', 'invitations');
            $document_type = 'product';
        } elseif ($entity instanceof RecurringInvoice) {
            $entity_type = 'recurring_invoice';
            $entity_design_id = 'invoice_design_id';

            $entity->load('client.company', 'invitations');
            $document_type = 'product';
        } elseif ($entity instanceof Quote) {
            $entity_type = 'quote';
            $entity_design_id = 'quote_design_id';

            $entity->load('client.company', 'invitations');
            $document_type = 'product';
        } elseif ($entity instanceof Credit) {
            $entity_type = 'product';

            $entity->load('client.company', 'invitations');
            $entity_design_id = 'credit_design_id';
            $document_type = 'credit';
        } elseif ($entity instanceof PurchaseOrder) {
            $entity_type = 'purchase_order';
            $entity_design_id = 'purchase_order_design_id';
            $document_type = 'purchase_order';
            $entity->load('vendor.company', 'invitations');
        }

        $entity_design_id = $entity->design_id ? $entity->design_id : $this->decodePrimaryKey($settings->{$entity_design_id});

        $design = Design::withTrashed()->find($entity_design_id);

        if (! $entity->invitations()->exists() || ! $design) {
            return '';
        }

        $ps = new PdfService($entity->invitations()->first(), $document_type, [
            'client' => $entity->client ?? false,
            'vendor' => $entity->vendor ?? false,
            "{$entity_type}s" => [$entity],
        ]);

        return $ps->boot()->getHtml();

    }
}
