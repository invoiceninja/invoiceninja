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

namespace App\Repositories\Migration;

use App\Jobs\Product\UpdateOrCreateProduct;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Repositories\BaseRepository;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Support\Carbon;
use ReflectionClass;

/**
 * InvoiceMigrationRepository.
 */
class InvoiceMigrationRepository extends BaseRepository
{
    use MakesHash;
    use SavesDocuments;

    public function save($data, $model)
    {
        $class = new ReflectionClass($model);

        if (array_key_exists('client_id', $data)) {
            $client = Client::where('id', $data['client_id'])->withTrashed()->first();
        } else {
            $client = Client::where('id', $model->client_id)->withTrashed()->first();
        }

        $state = [];
        $resource = explode('\\', $class->name)[2]; /** This will extract 'Invoice' from App\Models\Invoice */
        $lcfirst_resource_id = lcfirst($resource).'_id';

        if ($class->name == Invoice::class || $class->name == Quote::class || $class->name == RecurringInvoice::class) {
            $state['starting_amount'] = $model->amount;
        }

        if (! $model->id) {
            $company_defaults = $client->setCompanyDefaults($data, lcfirst($resource));
            $model->uses_inclusive_taxes = $client->getSetting('inclusive_taxes');
            $data = array_merge($company_defaults, $data);
        }

        $tmp_data = $data;

        if (array_key_exists('tax_rate1', $tmp_data) && is_null($tmp_data['tax_rate1'])) {
            $tmp_data['tax_rate1'] = 0;
        }

        if (array_key_exists('tax_rate2', $tmp_data) && is_null($tmp_data['tax_rate2'])) {
            $tmp_data['tax_rate2'] = 0;
        }

        /* We need to unset some variable as we sometimes unguard the model */

        if (isset($tmp_data['invitations'])) {
            unset($tmp_data['invitations']);
        }

        if (isset($tmp_data['client_contacts'])) {
            unset($tmp_data['client_contacts']);
        }

        $model->fill($tmp_data);
        $model->status_id = $tmp_data['status_id'];

        if ($tmp_data['created_at']) {
            $model->created_at = Carbon::parse($tmp_data['created_at']);
        }

        if ($tmp_data['updated_at']) {
            $model->updated_at = Carbon::parse($tmp_data['updated_at']);
        }

        $model->save(['timestamps' => false]);

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

        InvoiceInvitation::unguard();
        RecurringInvoiceInvitation::unguard();

        if ($model instanceof RecurringInvoice) {
            $lcfirst_resource_id = 'recurring_invoice_id';
        }

        foreach ($data['invitations'] as $invitation) {
            // nlog($invitation);

            $new_invitation = $invitation_factory_class::create($model->company_id, $model->user_id);
            $new_invitation->{$lcfirst_resource_id} = $model->id;
            $new_invitation->fill($invitation);
            $new_invitation->save();
        }

        InvoiceInvitation::reguard();
        RecurringInvoiceInvitation::reguard();

        $model->load('invitations');

        /* If no invitations have been created, this is our fail safe to maintain state*/
        if ($model->invitations->count() == 0) {
            $model->service()->createInvitations();
        }

        $model = $model->calc()->getInvoice();

        $state['finished_amount'] = $model->amount;

        $model = $model->service()->applyNumber()->save();

        if ($class->name == Invoice::class || $class->name == RecurringInvoice::class) {
            if (($state['finished_amount'] != $state['starting_amount']) && ($model->status_id != Invoice::STATUS_DRAFT)) {
            }

            if (! $model->design_id) {
                $model->design_id = $this->decodePrimaryKey($client->getSetting('invoice_design_id'));
            }
        }

        if ($class->name == Credit::class) {
            $model = $model->calc()->getCredit();

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

        if ($data['is_deleted']) {
            $model->is_deleted = true;
            $model->save();
        }

        if ($data['deleted_at'] == '0000-00-00 00:00:00.000000') {
            $model->deleted_at = null;
        } elseif ($data['deleted_at']) {
            $model->delete();
        }

        $model->save();

        return $model->fresh();
    }
}
