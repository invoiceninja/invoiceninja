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

namespace App\Repositories;

use App\Jobs\Client\UpdateTaxData;
use App\Jobs\Product\UpdateOrCreateProduct;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Vendor;
use App\Utils\BcMath;
use App\Utils\Helpers;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Validation\ValidationException;

class BaseRepository
{
    use MakesHash;
    use SavesDocuments;

    public bool $import_mode = false;

    private bool $new_model = false;

    /**
     * Whether the model handed to the current save() was a brand-new record.
     * Captured by resolveTagIdsForSync() before any write, because intermediate
     * save() calls (e.g. applyNumber()) reset Eloquent's wasRecentlyCreated.
     */
    private bool $record_was_created = false;

    /**
     * @param $entity
     * @param $type
     *
     * @return string
     */
    private function getEventClass($entity, $type)
    {
        return 'App\Events\\' . ucfirst(class_basename($entity)) . '\\' . ucfirst(class_basename($entity)) . 'Was' . $type;
    }

    /**
     * @param $entity
     */
    public function archive($entity)
    {
        if ($entity->trashed()) {
            return;
        }

        $entity->delete();

        $className = $this->getEventClass($entity, 'Archived');

        if (class_exists($className)) {
            event(new $className($entity, $entity->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }
    }

    /**
     * @param $entity
     */
    public function restore($entity)
    {
        if (!$entity->trashed()) {
            return;
        }

        $fromDeleted = false;

        if ($entity->is_deleted) {
            $fromDeleted = true;
            $entity->is_deleted = false;
            $entity->saveQuietly();
        }

        $entity->restore();

        $className = $this->getEventClass($entity, 'Restored');

        if (class_exists($className)) {
            event(new $className($entity, $fromDeleted, $entity->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }
    }

    /**
     * @param $entity
     */
    public function delete($entity)
    {
        if (!$entity || $entity->is_deleted) {
            return;
        }

        $entity->is_deleted = true;
        $entity->save();

        $entity->delete();

        $className = $this->getEventClass($entity, 'Deleted');

        if (class_exists($className) && !($entity instanceof Company)) {
            event(new $className($entity, $entity->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }
    }

    /* Returns an invoice if defined as a key in the $resource array*/
    public function getInvitation($invitation, $resource)
    {
        if (is_array($invitation) && !array_key_exists('key', $invitation)) {
            return false;
        }

        $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);

        $invitation = $invitation_class::with('company')->where('key', $invitation['key'])->first();

        return $invitation;
    }

    /* Clean return of a key rather than butchering the model*/
    private function resolveEntityKey($model)
    {
        switch ($model) {
            case ($model instanceof RecurringInvoice):
                return 'recurring_invoice_id';
            case ($model instanceof Invoice):
                return 'invoice_id';
            case ($model instanceof Quote):
                return 'quote_id';
            case ($model instanceof Credit):
                return 'credit_id';
        }
    }

    /**
     * Alternative save used for Invoices, Recurring Invoices, Quotes & Credits.
     *
     * @param $data
     * @param $model
     * @return mixed
     * @throws \ReflectionException
     */
    protected function alternativeSave($data, $model)
    {
        if (array_key_exists('client_id', $data)) {
            $model->client_id = $data['client_id'];
        }

        $tag_ids = $this->resolveTagIdsForSync($data, $model);

        $client = Client::query()->with('group_settings')->where('id', $model->client_id)->withTrashed()->firstOrFail();

        $state = [];

        $resource = class_basename($model); //ie Invoice

        $lcfirst_resource_id = $this->resolveEntityKey($model); //ie invoice_id

        $state['starting_amount'] = $model->balance;

        if (!$model->id) {
            $company_defaults = $client->setCompanyDefaults($data, lcfirst($resource));

            if ($this->import_mode && isset($data['exchange_rate']) && (float) $data['exchange_rate'] !== 0.0) {
                unset($company_defaults['exchange_rate']);
            }

            $model->uses_inclusive_taxes = $client->getSetting('inclusive_taxes');

            $data = array_merge($data, $company_defaults);
        }

        $tmp_data = $data; //preserves the $data array

        /* We need to unset some variable as we sometimes unguard the model */
        if (isset($tmp_data['invitations'])) {
            unset($tmp_data['invitations']);
        }

        if (isset($tmp_data['client_contacts'])) {
            unset($tmp_data['client_contacts']);
        }

        if (isset($tmp_data['tags'])) {
            unset($tmp_data['tags']);
        }

        $model->fill($tmp_data);

        $model->custom_surcharge_tax1 = $client->getSetting('e_invoice_type') == 'PEPPOL' ? true : $client->company->custom_surcharge_taxes1;
        $model->custom_surcharge_tax2 = $client->getSetting('e_invoice_type') == 'PEPPOL' ? true : $client->company->custom_surcharge_taxes2;
        $model->custom_surcharge_tax3 = $client->getSetting('e_invoice_type') == 'PEPPOL' ? true : $client->company->custom_surcharge_taxes3;
        $model->custom_surcharge_tax4 = $client->getSetting('e_invoice_type') == 'PEPPOL' ? true : $client->company->custom_surcharge_taxes4;

        if (!$model->id) {
            $this->new_model = true;

            if (is_array($model->line_items) && !($model instanceof RecurringInvoice)) {
                $model->line_items = (collect($model->line_items))->map(function ($item) use ($client) {
                    $item->notes = Helpers::processReservedKeywords($item->notes, $client);

                    return $item;
                });
            }
        }

        $model->saveQuietly();

        if (method_exists($model, 'searchable')) {
            $model->searchable();
        }

        /* Model now persisted, now lets do some child tasks */

        if ($model instanceof Invoice || $model instanceof Quote) {
            $model->service()->setReminder()->save();
        }

        /* Save any documents */
        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $model);
        }

        if (array_key_exists('file', $data)) {
            $this->saveDocuments($data['file'], $model);
        }

        $dn_enabled = $model->company->docuninjaActive();

        /* If invitations are present we need to filter existing invitations with the new ones */
        if (isset($data['invitations'])) {

            $invitations = collect($data['invitations']);

            /* Get array of Keys which have been removed from the invitations array and soft delete each invitation */
            $model->invitations->pluck('key')->diff($invitations->pluck('key'))->each(function ($invitation) use ($resource) {
                $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);
                $invitation = $invitation_class::query()->where('key', $invitation)->first();

                if ($invitation) {
                    $invitation->delete();
                }
            });

            foreach ($data['invitations'] as $invitation) {
                //if no invitations are present - create one.
                if ($invite = $this->getInvitation($invitation, $resource)) {
                    if ($dn_enabled) {
                        $invite->can_sign = $invitation['can_sign'] ?? false;
                        $invite->saveQuietly();
                    }
                } else {
                    if (isset($invitation['id'])) {
                        unset($invitation['id']);
                    }

                    //make sure we are creating an invite for a contact who belongs to the client only!
                    $contact = ClientContact::find($invitation['client_contact_id']);

                    if ($contact && $model->client_id == $contact->client_id) {
                        $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);

                        $new_invitation = $invitation_class::withTrashed()
                            ->where('client_contact_id', $contact->id)
                            ->where($lcfirst_resource_id, $model->id)
                            ->first();

                        if ($new_invitation && $new_invitation->trashed()) {
                            $new_invitation->restore();
                        } else {
                            $invitation_factory_class = sprintf('App\\Factory\\%sInvitationFactory', $resource);
                            $new_invitation = $invitation_factory_class::create($model->company_id, $model->user_id);
                            $new_invitation->{$lcfirst_resource_id} = $model->id;
                            $new_invitation->client_contact_id = $contact->id;
                            $new_invitation->key = $this->createDbHash($model->company->db);
                            $new_invitation->can_sign = $invitation['can_sign'] ?? false;
                            $new_invitation->saveQuietly();
                        }

                    }
                }

            }

        }

        /* If no invitations have been created, this is our fail safe to maintain state*/
        if ($model->invitations()->count() == 0) {
            $model->service()->createInvitations();
        }

        if ($dn_enabled && $model->invitations()->where('can_sign', true)->count() == 0) {
            $ii = $model->invitations()->whereHas('contact', function ($q) {
                $q->where('is_primary', true);
            })->first() ?? $model->invitations()->first();
            $ii->can_sign = true;
            $ii->saveQuietly();
        }

        /* Recalculate invoice amounts */
        $model = $model->calc()->getInvoice();

        /* Check if the model has been changed in a way that is relevant to Quickbooks */
        $qb_model_changes = $model->wasChanged(['amount', 'line_items', 'total_taxes']);

        /* We use this to compare to our starting amount */
        $state['finished_amount'] = $model->balance;

        /* Apply entity number */
        $model = $model->service()->applyNumber()->save();

        /* Handle attempts where the deposit is greater than the amount/balance of the invoice */
        if ((int) $model->balance != 0 && $model->partial > $model->amount && $model->amount > 0) {
            $model->partial = min($model->amount, $model->balance);
        }

        /* Update product details if necessary - if we are inside a transaction - do nothing */
        if ($model->company->update_products && $model->id && \DB::transactionLevel() == 0) {
            UpdateOrCreateProduct::dispatch($model->line_items, $model, $model->company);
        }

        /* Perform model specific tasks */
        if ($model instanceof Invoice) {
            if ($model->status_id != Invoice::STATUS_DRAFT) {
                $model->service()->updateStatus()->save();

                /** replaced with BcMath */
                // $adjustment = (float) BcMath::sub($state['finished_amount'], $state['starting_amount'], 2);
                // if ($adjustment != 0) {

                $adjustment = BcMath::sub($state['finished_amount'], $state['starting_amount'], 2);
                if (!BcMath::isZero($adjustment)) {
                    $model->client->service()->updateBalance((float) $adjustment);
                }

            }

            if (!$model->design_id) {
                $model->design_id = intval($this->decodePrimaryKey($model->client->getSetting('invoice_design_id')));
            }

            //links tasks and expenses back to the invoice, but only if we are not in the middle of a transaction.
            if (\DB::transactionLevel() == 0) {
                $model->service()->linkEntities()->save();
            }

            if ($this->new_model) {
                event('eloquent.created: App\Models\Invoice', $model);
            } else {
                event('eloquent.updated: App\Models\Invoice', $model);
            }

            /** Quickbooks Sync Logic */
            if ($qb_model_changes && $model->company->quickbooks && empty(\App\Services\Quickbooks\QuickbooksService::$importing[$model->company_id]) && $model->company->shouldPushToQuickbooks('invoice')) {

                if ($model->company->quickbooks->settings->automatic_taxes) {

                    try {
                        (new \App\Jobs\Quickbooks\PushToQuickbooks('invoice', $model->id, $model->company->db))->handle();
                    } catch (\Throwable $e) {
                        app('sentry')->captureException($e);
                        nlog("Quickbooks push to Quickbooks job failed => " . $e->getMessage());
                    }
                } elseif ($model->status_id != Invoice::STATUS_DRAFT) {
                    \App\Services\Quickbooks\QuickbooksBatchCollector::collect('invoice', $model->id, $model->company->db, $model->company_id);
                }

            }

            /** If the client does not have tax_data - then populate this now */
            if ($client->country_id == 840 && !$client->tax_data && $model->company->calculate_taxes && !$model->company->account->isFreeHostedClient()) {
                UpdateTaxData::dispatch($client, $client->company);
            }

            /** If Peppol is enabled - we will save the e_invoice document here at this point, document will not update after being sent */
            if ((!isset($model->backup) || !property_exists($model->backup, 'guid')) && $client->getSetting('e_invoice_type') == 'PEPPOL' && $model->company->legal_entity_id) {
                try {
                    $model->service()->getEInvoice();
                } catch (\Throwable $e) {
                    nlog("EXCEPTION:: BASEREPOSITORY:: Error generating e_invoice for model {$model->id}");
                    nlog($e->getMessage());
                }
            }

            /** Verifactu modified invoice check */
            if ($model->company->verifactuEnabled()) {
                $model->service()->modifyVerifactuWorkflow($data, $this->new_model)->save();
            }
        }

        if ($model instanceof Credit) {
            $model = $model->calc()->getCredit();

            if (!$model->design_id) {
                $model->design_id = $this->decodePrimaryKey($client->getSetting('credit_design_id'));
            }

            if (array_key_exists('invoice_id', $data) && $data['invoice_id']) {
                $model->invoice_id = $data['invoice_id'];
            }

            if ($this->new_model) {
                event('eloquent.created: App\Models\Credit', $model);
            } else {
                event('eloquent.updated: App\Models\Credit', $model);
            }

            if (($state['finished_amount'] != $state['starting_amount']) && ($model->status_id != Credit::STATUS_DRAFT)) {
                $model->client->service()->adjustCreditBalance(($state['finished_amount'] - $state['starting_amount']))->save();
            }
        }

        if ($model instanceof Quote) {
            if (!$model->design_id) {
                $model->design_id = intval($this->decodePrimaryKey($model->client->getSetting('quote_design_id')));
            }

            $model = $model->calc()->getQuote();

            if ($this->new_model) {
                event('eloquent.created: App\Models\Quote', $model);
            } else {
                event('eloquent.updated: App\Models\Quote', $model);
            }
        }

        if ($model instanceof RecurringInvoice) {
            if (!$model->design_id) {
                $model->design_id = intval($this->decodePrimaryKey($model->client->getSetting('invoice_design_id')));
            }

            $model = $model->calc()->getRecurringInvoice();

            $model->status_id = $model->calculateStatus($this->new_model);

            if ($this->new_model) {
                event('eloquent.created: App\Models\RecurringInvoice', $model);
            } else {
                event('eloquent.updated: App\Models\RecurringInvoice', $model);
            }
        }

        $model->saveQuietly();

        $this->syncResolvedTags($model, $tag_ids);

        return $model->fresh();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int>|null
     */
    protected function resolveTagIdsForSync(array &$data, object $model): ?array
    {
        $this->record_was_created = ! $model->exists;

        if (! array_key_exists('tags', $data)) {
            return null;
        }

        if (! method_exists($model, 'tags') || ! method_exists($model, 'resolveTagIds')) {
            unset($data['tags']);

            return null;
        }

        if (! is_array($data['tags'])) {
            throw ValidationException::withMessages([
                'tags' => ['One or more tags are invalid for this entity.'],
            ]);
        }

        $tag_ids = $model::resolveTagIds($data['tags'], (int) $model->company_id);

        unset($data['tags']);

        return $tag_ids;
    }

    /**
     * @param array<int>|null $tag_ids
     */
    protected function syncResolvedTags(object $model, ?array $tag_ids): void
    {
        if (! method_exists($model, 'tags')) {
            return;
        }

        $changed = false;

        $inherited_tag_ids = $this->inheritedGlobalTagIds($model);

        if ($tag_ids !== null || ! empty($inherited_tag_ids)) {
            $model->tags()->sync(array_values(array_unique(array_merge($tag_ids ?? [], $inherited_tag_ids))));
            $changed = true;
        }

        if ($this->rollUpInvoicedTags($model)) {
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        if (method_exists($model, 'touchQuietly')) {
            $model->touchQuietly();
        }

        if (method_exists($model, 'searchable')) {
            $model->searchable();
        }
    }

    /**
     * Foreign keys on a child record that point at a taggable "parent" whose
     * GLOBAL tags should cascade down. A single record may have several parents
     * at once (e.g. an invoice belongs to both a client and a project) and
     * inherits the union of their global tags.
     *
     * @var array<string, class-string>
     */
    protected array $global_tag_parents = [
        'client_id' => Client::class,
        'vendor_id' => Vendor::class,
        'project_id' => Project::class,
    ];

    /**
     * When global_tag_inheritance is enabled, a record that has just been
     * created inherits the GLOBAL tags (entity_type = Company) assigned to each
     * of its parents (client, vendor, project). Gated on wasRecentlyCreated so
     * it applies exactly once - at initial creation - and never on updates.
     *
     * @return array<int>
     */
    protected function inheritedGlobalTagIds(object $model): array
    {
        if (! $this->record_was_created) {
            return [];
        }

        if (! $model->company || ! $model->company->getSetting('global_tag_inheritance')) {
            return [];
        }

        $tag_ids = [];

        foreach ($this->global_tag_parents as $foreign_key => $parent_class) {
            $parent_id = $model->{$foreign_key} ?? null;

            if (! $parent_id || $model instanceof $parent_class) {
                continue;
            }

            $tag_ids = array_merge(
                $tag_ids,
                $this->globalTagIdsForParent($parent_class, (int) $parent_id, (int) $model->company_id)
            );
        }

        return array_values(array_unique($tag_ids));
    }

    /**
     * @param  class-string $parent_class
     * @return array<int>
     */
    private function globalTagIdsForParent(string $parent_class, int $parent_id, int $company_id): array
    {
        $parent = $parent_class::withTrashed()
            ->where('company_id', $company_id)
            ->find($parent_id);

        if (! $parent || ! method_exists($parent, 'tags')) {
            return [];
        }

        return $parent->tags()
            ->where('tags.entity_type', Tag::GLOBAL_ENTITY_TYPE)
            ->where('tags.is_deleted', false)
            ->pluck('tags.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Rolls the GLOBAL tags of the tasks/expenses billed by an invoice up onto
     * that invoice. Unlike the ownership cascade this is purely additive
     * (syncWithoutDetaching) and runs on every save, since line items - and the
     * entities they reference - can be added on later edits, not just creation.
     */
    private function rollUpInvoicedTags(object $model): bool
    {
        if (! $model instanceof Invoice) {
            return false;
        }

        if (! $model->company->getSetting('global_tag_inheritance')) {
            return false;
        }

        $task_ids = [];
        $expense_ids = [];

        foreach ($model->line_items ?? [] as $item) {
            if (! empty($item->task_id) && ($id = $this->decodePrimaryKey($item->task_id)) > 0) {
                $task_ids[] = (int) $id;
            }

            if (! empty($item->expense_id) && ($id = $this->decodePrimaryKey($item->expense_id)) > 0) {
                $expense_ids[] = (int) $id;
            }
        }

        $rollup_tag_ids = $this->globalTagIdsForTaggables($task_ids, $expense_ids, (int) $model->company_id);

        if (empty($rollup_tag_ids)) {
            return false;
        }

        $result = $model->tags()->syncWithoutDetaching($rollup_tag_ids);

        return ! empty($result['attached']);
    }

    /**
     * GLOBAL tag ids attached to any of the given tasks or expenses, resolved in
     * a single query against the taggables pivot.
     *
     * @param  array<int> $task_ids
     * @param  array<int> $expense_ids
     * @return array<int>
     */
    private function globalTagIdsForTaggables(array $task_ids, array $expense_ids, int $company_id): array
    {
        if (empty($task_ids) && empty($expense_ids)) {
            return [];
        }

        return Tag::query()
            ->where('tags.company_id', $company_id)
            ->where('tags.entity_type', Tag::GLOBAL_ENTITY_TYPE)
            ->where('tags.is_deleted', false)
            ->whereExists(function ($query) use ($task_ids, $expense_ids): void {
                $query->selectRaw('1')
                    ->from('taggables')
                    ->whereColumn('taggables.tag_id', 'tags.id')
                    ->where(function ($q) use ($task_ids, $expense_ids): void {
                        if (! empty($task_ids)) {
                            $q->orWhere(fn ($w) => $w->where('taggables.taggable_type', Task::class)->whereIn('taggables.taggable_id', $task_ids));
                        }

                        if (! empty($expense_ids)) {
                            $q->orWhere(fn ($w) => $w->where('taggables.taggable_type', Expense::class)->whereIn('taggables.taggable_id', $expense_ids));
                        }
                    });
            })
            ->pluck('tags.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function bulkUpdate(\Illuminate\Database\Eloquent\Builder $model, string $column, mixed $new_value): void
    {
        /** Handle taxes being updated */
        if (in_array($column, ['tax1','tax2','tax3'])) {

            $parts = explode("||", $new_value);
            $tax_name_column = str_replace("tax", "tax_name", $column);
            $tax_rate_column = str_replace("tax", "tax_rate", $column);

            /** Harvest the tax name and rate */
            if (count($parts) == 2) {

                $rate = filter_var($parts[1], FILTER_VALIDATE_FLOAT);
                $tax_name = $parts[0];

                if ($rate === false) {
                    return;
                }

            } else { //else we need to clear the value

                $rate = 0;
                $tax_name = "";

            }

            $model->update([
                $tax_name_column => $tax_name,
                $tax_rate_column => $rate,
            ]);

            return;
        }

        if ($column == 'project_id') {
            $project_id = $this->decodePrimaryKey($new_value);

            $project = Project::withTrashed()
                ->where('id', $project_id)
                ->company()
                ->first();

            if (! $project) {
                throw ValidationException::withMessages([
                    'new_value' => ['The selected new value is invalid.'],
                ]);
            }

            $model->update([
                'project_id' => $project->id,
                'client_id' => $project->client_id,
            ]);

            return;
        }

        if ($column == 'client_id') {
            $client_id = $this->decodePrimaryKey($new_value);

            $client = Client::withTrashed()
                ->where('id', $client_id)
                ->company()
                ->first();

            if (! $client) {
                throw ValidationException::withMessages([
                    'new_value' => ['The selected new value is invalid.'],
                ]);
            }

            $model->update([
                'client_id' => $client->id,
                'project_id' => null,
            ]);

            return;
        }

        $model->update([$column => $new_value]);
    }
}
