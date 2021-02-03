<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
            event(new $className($entity, $entity->company, Ninja::eventVars()));
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
            event(new $className($entity, $fromDeleted, $entity->company, Ninja::eventVars()));
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
            event(new $className($entity, $entity->company, Ninja::eventVars()));
        }
    }

    /**
     * @param $ids
     * @param $action
     *
     * @return int
     */
    public function bulk($ids, $action)
    {
        if (! $ids) {
            return 0;
        }

        $ids = $this->transformKeys($ids);

        $entities = $this->findByPublicIdsWithTrashed($ids);

        foreach ($entities as $entity) {
            if (auth()->user()->can('edit', $entity)) {
                $this->$action($entity);
            }
        }

        return count($entities);
    }

    /* Returns an invoice if defined as a key in the $resource array*/
    public function getInvitation($invitation, $resource)
    {
        if (is_array($invitation) && ! array_key_exists('key', $invitation))
            return false;
        
        $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);

        $invitation = $invitation_class::whereRaw('BINARY `key`= ?', [$invitation['key']])->first();

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

        if (array_key_exists('client_id', $data)) //forces the client_id if it doesn't exist
            $model->client_id = $data['client_id'];

        $client = Client::where('id', $model->client_id)->withTrashed()->first();    

        $state = [];

        $resource = class_basename($model); //ie Invoice

        $lcfirst_resource_id = $this->resolveEntityKey($model); //ie invoice_id

        $state['starting_amount'] = $model->amount;

        if (! $model->id) {
            $company_defaults = $client->setCompanyDefaults($data, lcfirst($resource));
            $model->uses_inclusive_taxes = $client->getSetting('inclusive_taxes');
            $data = array_merge($company_defaults, $data);
        }

        $tmp_data = $data; //preserves the $data arrayss

        /* We need to unset some variable as we sometimes unguard the model */
        if (isset($tmp_data['invitations'])) 
            unset($tmp_data['invitations']);
        
        if (isset($tmp_data['client_contacts'])) 
            unset($tmp_data['client_contacts']);
        
        $model->fill($tmp_data);
        $model->save();

        /* Model now persisted, now lets do some child tasks */

        /* Save any documents */
        if (array_key_exists('documents', $data)) 
            $this->saveDocuments($data['documents'], $model);

        /* Marks whether the client contact should receive emails based on the send_email property */
        if (isset($data['client_contacts'])) {
            foreach ($data['client_contacts'] as $contact) {
                if ($contact['send_email'] == 1 && is_string($contact['id'])) {
                    $client_contact = ClientContact::find($this->decodePrimaryKey($contact['id']));
                    $client_contact->send_email = true;
                    $client_contact->save();
                }
            }
        }

        /* If invitations are present we need to filter existing invitations with the new ones */
        if (isset($data['invitations'])) {
            $invitations = collect($data['invitations']);

            /* Get array of Keys which have been removed from the invitations array and soft delete each invitation */
            $model->invitations->pluck('key')->diff($invitations->pluck('key'))->each(function ($invitation) use ($resource) {
                $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);
                $invitation = $invitation_class::whereRaw('BINARY `key`= ?', [$invitation])->first();

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
                            $new_invitation->save();

                        }
                    }
                }
            }
        }

        $model->load('invitations');

        /* If no invitations have been created, this is our fail safe to maintain state*/
        if ($model->invitations->count() == 0) 
            $model->service()->createInvitations();

        /* Recalculate invoice amounts */
        $model = $model->calc()->getInvoice();

        /* We use this to compare to our starting amount */
        $state['finished_amount'] = $model->amount;

        /* Apply entity number */
        $model = $model->service()->applyNumber()->save();

        /* Update product details if necessary */
        if ($model->company->update_products !== false) 
            UpdateOrCreateProduct::dispatch($model->line_items, $model, $model->company);

        /* Perform model specific tasks */
        if ($model instanceof Invoice) {

            if (($state['finished_amount'] != $state['starting_amount']) && ($model->status_id != Invoice::STATUS_DRAFT)) {

                $model->ledger()->updateInvoiceBalance(($state['finished_amount'] - $state['starting_amount']), "Update adjustment for invoice {$model->number}");
                $model->client->service()->updateBalance(($state['finished_amount'] - $state['starting_amount']))->save();

            }

            if (! $model->design_id) 
                $model->design_id = $this->decodePrimaryKey($client->getSetting('invoice_design_id'));

            //links tasks and expenses back to the invoice.
            $model->service()->linkEntities()->save();

        }

        if ($model instanceof Credit) {

            $model = $model->calc()->getCredit();

            // $model->ledger()->updateCreditBalance(-1*($state['finished_amount'] - $state['starting_amount']));

            if (! $model->design_id) 
                $model->design_id = $this->decodePrimaryKey($client->getSetting('credit_design_id'));
            
        }

        if ($model instanceof Quote) {

            $model = $model->calc()->getQuote();

        }

        if ($model instanceof RecurringInvoice) {

            $model = $model->calc()->getRecurringInvoice();

        }

        $model->save();

        return $model->fresh();
    }
}
