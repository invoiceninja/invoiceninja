<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Jobs\Product\UpdateOrCreateProduct;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use ReflectionClass;

class BaseRepository
{
    use MakesHash;
    use SavesDocuments;

	public bool $import_mode = false;

    private bool $new_model = false;
    /**
     * @param $entity
     * @param $type
     *
     * @return string
     */
    private function getEventClass($entity, $type)
    {
        return 'App\Events\\'.ucfirst(class_basename($entity)).'\\'.ucfirst(class_basename($entity)).'Was'.$type;
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
        if (! $entity->trashed()) {
            return;
        }

        $fromDeleted = false;

        $entity->restore();

        if ($entity->is_deleted) {
            $fromDeleted = true;
            $entity->is_deleted = false;
            $entity->save();
        }

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
        if ($entity->is_deleted) {
            return;
        }

        $entity->is_deleted = true;
        $entity->save();

        $entity->delete();

        $className = $this->getEventClass($entity, 'Deleted');

        if (class_exists($className) && ! ($entity instanceof Company)) {
            event(new $className($entity, $entity->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }
    }

    /**
     * @param $ids
     * @param $action
     *
     * @return int
     * @deprecated - this doesn't appear to be used anywhere?
     */
    // public function bulk($ids, $action)
    // {
    //     if (! $ids) {
    //         return 0;
    //     }

    //     $ids = $this->transformKeys($ids);

    //     $entities = $this->findByPublicIdsWithTrashed($ids);

    //     foreach ($entities as $entity) {
    //         if (auth()->user()->can('edit', $entity)) {
    //             $this->$action($entity);
    //         }
    //     }

    //     return count($entities);
    // }

    /* Returns an invoice if defined as a key in the $resource array*/
    public function getInvitation($invitation, $resource)
    {
        if (is_array($invitation) && ! array_key_exists('key', $invitation))
            return false;
        
        $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);

        $invitation = $invitation_class::where('key', $invitation['key'])->first();

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
        //forces the client_id if it doesn't exist
        if(array_key_exists('client_id', $data)) 
            $model->client_id = $data['client_id'];

        $client = Client::where('id', $model->client_id)->withTrashed()->firstOrFail();    

        $state = [];

        $resource = class_basename($model); //ie Invoice

        $lcfirst_resource_id = $this->resolveEntityKey($model); //ie invoice_id

        $state['starting_amount'] = $model->amount;

        if (! $model->id) {
            $company_defaults = $client->setCompanyDefaults($data, lcfirst($resource));
            $model->uses_inclusive_taxes = $client->getSetting('inclusive_taxes');
            $data = array_merge($company_defaults, $data);
        }

        $tmp_data = $data; //preserves the $data array

        /* We need to unset some variable as we sometimes unguard the model */
        if (isset($tmp_data['invitations'])) 
            unset($tmp_data['invitations']);
        
        if (isset($tmp_data['client_contacts'])) 
            unset($tmp_data['client_contacts']);
        
        $model->fill($tmp_data);

        $model->custom_surcharge_tax1 = $client->company->custom_surcharge_taxes1;
        $model->custom_surcharge_tax2 = $client->company->custom_surcharge_taxes2;
        $model->custom_surcharge_tax3 = $client->company->custom_surcharge_taxes3;
        $model->custom_surcharge_tax4 = $client->company->custom_surcharge_taxes4;

        if(!$model->id)
            $this->new_model = true;
        
        $model->saveQuietly();

        /* Model now persisted, now lets do some child tasks */

        if($model instanceof Invoice)
            $model->service()->setReminder()->save();

        /* Save any documents */
        if (array_key_exists('documents', $data)) 
            $this->saveDocuments($data['documents'], $model);

        if (array_key_exists('file', $data)) 
            $this->saveDocuments($data['file'], $model);

        /* If invitations are present we need to filter existing invitations with the new ones */
        if (isset($data['invitations'])) {
            $invitations = collect($data['invitations']);

            /* Get array of Keys which have been removed from the invitations array and soft delete each invitation */
            $model->invitations->pluck('key')->diff($invitations->pluck('key'))->each(function ($invitation) use ($resource) {
                $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);
                $invitation = $invitation_class::where('key', $invitation)->first();

                if ($invitation) 
                    $invitation->delete();
                
            });

            foreach ($data['invitations'] as $invitation) {

                //if no invitations are present - create one.
                if (! $this->getInvitation($invitation, $resource)) {

                    if (isset($invitation['id'])) 
                        unset($invitation['id']);

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
                            $new_invitation->save();

                        }
                    }
                }
            }
        }

        /* If no invitations have been created, this is our fail safe to maintain state*/
        if ($model->invitations()->count() == 0) 
            $model->service()->createInvitations();

        /* Recalculate invoice amounts */
        $model = $model->calc()->getInvoice();

        /* We use this to compare to our starting amount */
        $state['finished_amount'] = $model->amount;

        /* Apply entity number */
        $model = $model->service()->applyNumber()->save();

        /* Handle attempts where the deposit is greater than the amount/balance of the invoice */
        if((int)$model->balance != 0 && $model->partial > $model->amount)
            $model->partial = min($model->amount, $model->balance);

        /* Update product details if necessary - if we are inside a transaction - do nothing */
        if ($model->company->update_products && $model->id && \DB::transactionLevel() == 0) 
            UpdateOrCreateProduct::dispatch($model->line_items, $model, $model->company);

        /* Perform model specific tasks */
        if ($model instanceof Invoice) {

            if (($state['finished_amount'] != $state['starting_amount']) && ($model->status_id != Invoice::STATUS_DRAFT)) {

                //10-07-2022
                $model->service()->updateStatus()->save();
                $model->client->service()->updateBalance(($state['finished_amount'] - $state['starting_amount']))->save();
                $model->ledger()->updateInvoiceBalance(($state['finished_amount'] - $state['starting_amount']), "Update adjustment for invoice {$model->number}");

            }

            if (! $model->design_id) 
                $model->design_id = $this->decodePrimaryKey($client->getSetting('invoice_design_id'));

            //links tasks and expenses back to the invoice.
            $model->service()->linkEntities()->save();

            if($this->new_model)
                event('eloquent.created: App\Models\Invoice', $model);
            else
                event('eloquent.updated: App\Models\Invoice', $model);

        }

        if ($model instanceof Credit) {

            $model = $model->calc()->getCredit();

            if (! $model->design_id) 
                $model->design_id = $this->decodePrimaryKey($client->getSetting('credit_design_id'));

            if(array_key_exists('invoice_id', $data) && $data['invoice_id'])
                $model->invoice_id = $data['invoice_id'];

            if($this->new_model)
                event('eloquent.created: App\Models\Credit', $model);            
            else
                event('eloquent.updated: App\Models\Credit', $model);

        }

        if ($model instanceof Quote) {

            if (! $model->design_id) 
                $model->design_id = $this->decodePrimaryKey($client->getSetting('quote_design_id'));

            $model = $model->calc()->getQuote();


            if($this->new_model)
                event('eloquent.created: App\Models\Quote', $model);
            else
                event('eloquent.updated: App\Models\Quote', $model);
        }

        if ($model instanceof RecurringInvoice) {

            if (! $model->design_id) 
                $model->design_id = $this->decodePrimaryKey($client->getSetting('invoice_design_id'));
            
            $model = $model->calc()->getRecurringInvoice();


            if($this->new_model)
                event('eloquent.created: App\Models\RecurringInvoice', $model);
            else
                event('eloquent.updated: App\Models\RecurringInvoice', $model);
        }

        $model->save();

        return $model->fresh();
    }
}
