<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Events\Credit\CreditWasUpdated;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Events\Quote\QuoteWasUpdated;
use App\Factory\InvoiceInvitationFactory;
use App\Factory\QuoteInvitationFactory;
use App\Jobs\Product\UpdateOrCreateProduct;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Quote;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use ReflectionClass;

class BaseRepository
{
    use MakesHash;
    use SavesDocuments;

    /**
     * @return null
     */
    public function getClassName()
    {
        return null;
    }

    /**
     * @return mixed
     */
    private function getInstance()
    {
        $className = $this->getClassName();

        return new $className();
    }

    /**
     * @param $entity
     * @param $type
     *
     * @return string
     */
    private function getEventClass($entity, $type)
    {
        return 'App\Events\\'.ucfirst(class_basename($entity)).'Was'.$type;
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

    /**
     * @param $ids
     *
     * @return mixed
     */
    public function findByPublicIds($ids)
    {
        return $this->getInstance()->scope($ids)->get();
    }

    /**
     * @param $ids
     *
     * @return mixed
     */
    public function findByPublicIdsWithTrashed($ids)
    {
        return $this->getInstance()->scope($ids)->withTrashed()->get();
    }

    public function getInvitation($invitation, $resource)
    {
        if (is_array($invitation) && ! array_key_exists('key', $invitation)) {
            return false;
        }

        $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);

        $invitation = $invitation_class::whereRaw('BINARY `key`= ?', [$invitation['key']])->first();

        return $invitation;
    }

    /**
     * Alternative save used for Invoices, Quotes & Credits.
     * @param $data
     * @param $model
     * @return mixed
     * @throws \ReflectionException
     */
    protected function alternativeSave($data, $model)
    {
        $class = new ReflectionClass($model);

        if (array_key_exists('client_id', $data)) {
            $client = Client::find($data['client_id']);
        } else {
            $client = Client::find($model->client_id);
        }

        $state = [];
        $resource = explode('\\', $class->name)[2]; /** This will extract 'Invoice' from App\Models\Invoice */
        $lcfirst_resource_id = lcfirst($resource).'_id';

        $state['starting_amount'] = $model->amount;

        if (! $model->id) {
            $company_defaults = $client->setCompanyDefaults($data, lcfirst($resource));
            $model->uses_inclusive_taxes = $client->getSetting('inclusive_taxes');
            $data = array_merge($company_defaults, $data);
        }

        $tmp_data = $data;

        /* We need to unset some variable as we sometimes unguard the model */
        if (isset($tmp_data['invitations'])) {
            unset($tmp_data['invitations']);
        }

        if (isset($tmp_data['client_contacts'])) {
            unset($tmp_data['client_contacts']);
        }

        $model->fill($tmp_data);
        $model->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $model);
        }

        $invitation_factory_class = sprintf('App\\Factory\\%sInvitationFactory', $resource);

        if (isset($data['client_contacts'])) {
            foreach ($data['client_contacts'] as $contact) {
                if ($contact['send_email'] == 1 && is_string($contact['id'])) {
                    $client_contact = ClientContact::find($this->decodePrimaryKey($contact['id']));
                    $client_contact->send_email = true;
                    $client_contact->save();
                }
            }
        }

        if (isset($data['invitations'])) {
            $invitations = collect($data['invitations']);

            /* Get array of Keys which have been removed from the invitations array and soft delete each invitation */
            $model->invitations->pluck('key')->diff($invitations->pluck('key'))->each(function ($invitation) use ($resource) {
                $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);
                $invitation = $invitation_class::whereRaw('BINARY `key`= ?', [$invitation])->first();

                if ($invitation) {
                    $invitation->delete();
                }
            });

            foreach ($data['invitations'] as $invitation) {

                //if no invitations are present - create one.
                if (! $this->getInvitation($invitation, $resource)) {
                    if (isset($invitation['id'])) {
                        unset($invitation['id']);
                    }

                    //make sure we are creating an invite for a contact who belongs to the client only!
                    $contact = ClientContact::find($invitation['client_contact_id']);

                    if ($contact && $model->client_id == $contact->client_id)
                    {

                        $invitation_class = sprintf('App\\Models\\%sInvitation', $resource);

                        $new_invitation = $invitation_class::withTrashed()
                                            ->where('client_contact_id', $contact->id)
                                            ->where($lcfirst_resource_id, $model->id)
                                            ->first();

                        if ($new_invitation && $new_invitation->trashed()) {
                            $new_invitation->restore();
                        } else {
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
        if ($model->invitations->count() == 0) {
            $model->service()->createInvitations();
        }

        $model = $model->calc()->getInvoice();

        $state['finished_amount'] = $model->amount;

        $model = $model->service()->applyNumber()->save();

        if ($model->company->update_products !== false) {
            UpdateOrCreateProduct::dispatch($model->line_items, $model, $model->company);
        }

        if ($class->name == Invoice::class) {
            if (($state['finished_amount'] != $state['starting_amount']) && ($model->status_id != Invoice::STATUS_DRAFT)) {
                $model->ledger()->updateInvoiceBalance(($state['finished_amount'] - $state['starting_amount']));
                $model->client->service()->updateBalance(($state['finished_amount'] - $state['starting_amount']))->save();
            }

            if (! $model->design_id) {
                $model->design_id = $this->decodePrimaryKey($client->getSetting('invoice_design_id'));
            }
        }

        if ($class->name == Credit::class) {

            $model = $model->calc()->getCredit();

            $model->ledger()->updateCreditBalance(($state['finished_amount'] - $state['starting_amount']));

            if (! $model->design_id) {
                $model->design_id = $this->decodePrimaryKey($client->getSetting('credit_design_id'));
            }
        }

        if ($class->name == Quote::class) {
            $model = $model->calc()->getQuote();

            if (! $model->design_id) {
                $model->design_id = $this->decodePrimaryKey($client->getSetting('quote_design_id'));
            }
        }

        $model->save();

        return $model->fresh();
    }
}
