<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories\Migration;

use App\Events\Payment\PaymentWasCreated;
use App\Factory\CreditFactory;
use App\Jobs\Credit\ApplyCreditPayment;
use App\Jobs\Product\UpdateOrCreateProduct;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Repositories\ActivityRepository;
use App\Repositories\BaseRepository;
use App\Repositories\CreditRepository;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use ReflectionClass;

/**
 * InvoiceMigrationRepository
 */
class InvoiceMigrationRepository extends BaseRepository
{
    use MakesHash;
    use SavesDocuments;

    public function save($data, $model)
    {

        $class = new ReflectionClass($model);

        if (array_key_exists('client_id', $data)) {
            $client = Client::find($data['client_id']);
        } else {
            $client = Client::find($model->client_id);
        }

        $state = [];
        $resource = explode('\\', $class->name)[2]; /** This will extract 'Invoice' from App\Models\Invoice */
        $lcfirst_resource_id = lcfirst($resource) . '_id';

        if ($class->name == Invoice::class || $class->name == Quote::class) {
            $state['starting_amount'] = $model->amount;
        }

        if (!$model->id) {
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

        $invitation_factory_class = sprintf("App\\Factory\\%sInvitationFactory", $resource);

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
            $model->invitations->pluck('key')->diff($invitations->pluck('key'))->each(function ($invitation) {
                $this->getInvitation($invitation, $resource)->delete();
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
                        $new_invitation = $invitation_factory_class::create($model->company_id, $model->user_id);
                        $new_invitation->{$lcfirst_resource_id} = $model->id;
                        $new_invitation->client_contact_id = $contact->id;
                        $new_invitation->save();
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

                // $model->ledger()->updateInvoiceBalance(($state['finished_amount'] - $state['starting_amount']));
                // $model->client->service()->updateBalance(($state['finished_amount'] - $state['starting_amount']))->save();
            }

            if(!$model->design_id)
                $model->design_id = $this->decodePrimaryKey($client->getSetting('invoice_design_id'));

        }

        if ($class->name == Credit::class) {
            $model = $model->calc()->getCredit();

            if(!$model->design_id)
                $model->design_id = $this->decodePrimaryKey($client->getSetting('credit_design_id'));


        }
        
        if ($class->name == Quote::class) {
            $model = $model->calc()->getQuote();

            if(!$model->design_id)
                $model->design_id = $this->decodePrimaryKey($client->getSetting('quote_design_id'));



        }

        $model->save();

        return $model->fresh();
    }
    
}
