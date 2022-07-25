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

namespace App\Services\Recurring;

use App\Services\AbstractService;
use App\Utils\Traits\MakesHash;
use Exception;
use Illuminate\Support\Str;

class CreateRecurringInvitations extends AbstractService
{
    use MakesHash;

    private $entity;

    private $entity_name;

    private $entity_id_name;

    private $invitation_class;

    private $invitation_factory;

    public function __construct($entity)
    {
        $this->entity = $entity;
        $this->entity_name = lcfirst(Str::snake(class_basename($entity)));
        $this->entity_id_name = $this->entity_name.'_id';
        $this->invitation_class = 'App\Models\\'.ucfirst(Str::camel($this->entity_name)).'Invitation';
        $this->invitation_factory = 'App\Factory\\'.ucfirst(Str::camel($this->entity_name)).'InvitationFactory';
    }

    public function run()
    {
        try {
            $this->entity->client->contacts->each(function ($contact) {
                $invitation = $this->invitation_class::whereCompanyId($this->entity->company_id)
                                            ->whereClientContactId($contact->id)
                                            ->where($this->entity_id_name, $this->entity->id)
                                            ->withTrashed()
                                            ->first();

                if (! $invitation && $contact->send_email) {
                    $ii = $this->invitation_factory::create($this->entity->company_id, $this->entity->user_id);
                    $ii->key = $this->createDbHash($this->entity->company->db);
                    $ii->{$this->entity_id_name} = $this->entity->id;
                    $ii->client_contact_id = $contact->id;
                    $ii->save();
                } elseif ($invitation && ! $contact->send_email) {
                    $invitation->delete();
                }
            });
        } catch (Exception $e) {
            info($e->getMessage());
        }

        return $this->entity;
    }
}
