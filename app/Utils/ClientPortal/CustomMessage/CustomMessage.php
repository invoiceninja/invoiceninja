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

namespace App\Utils\ClientPortal\CustomMessage;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\GroupSetting;

class CustomMessage
{
    protected $message;

    protected $values;

    protected $client;

    protected $company;

    protected $contact;

    protected $group;

    protected $entity;

    protected $invitation;

    public function client(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function company(Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function contact(ClientContact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function group(GroupSetting $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function entity($entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function invitation($invitation): self
    {
        $this->invitation = $invitation;

        return $this;
    }

    public function message(string $message): string
    {
        $this->message = $message;
        $this->values = $this->compose();

        return strtr($message, $this->values);
    }

    private function compose(): array
    {
        return [
            '$company.id' => optional($this->company)->id,
            '$company.name' => optional($this->company)->getSetting('name'),
            '$company.website' => optional($this->company)->getSetting('website'),

            '$client.id' => optional($this->client)->id,
            '$client.name' => optional($this->client)->name,
            '$client.website' => optional($this->client)->website,
            '$client.public_notes' => optional($this->client)->public_notes,
            '$client.phone' => optional($this->client)->phone,
            '$client.balance' => optional($this->client)->balance,
            '$client.address1' => optional(optional($this->client)->present())->address1,
            '$client.address2' => optional(optional($this->client)->present())->address2,
            '$client.city' => optional(optional($this->client)->present())->city,
            '$client.state' => optional(optional($this->client)->present())->state,
            '$client.postal_code' => optional(optional($this->client)->present())->postal_code,
            '$client.country' => optional(optional(optional($this->client)->present())->country)->full_name,
            '$client.address' => optional(optional($this->client)->present())->address(),
            '$client.shipping_address' => optional(optional($this->client)->present())->shipping_address(),
            '$client.primary_contact_name' => optional(optional($this->client)->present())->primary_contact_name(),
            '$client.city_state' => optional(optional($this->client)->present())->getCityState(),

            '$contact.first_name' => optional($this->contact)->first_name,
            '$contact.last_name' => optional($this->contact)->last_name,
            '$contact.phone' => optional($this->contact)->phone,
            '$contact.email' => optional($this->contact)->email,
            '$contact.avatar' => optional($this->contact)->avatar,

            '$group.id' => optional($this->group)->id,

            '$entity.id' => optional($this->entity)->hashed_id,
            '$entity.number' => optional($this->entity)->number,
            '$entity.discount' => optional($this->entity)->discount,
            '$entity.date' => optional($this->entity)->date, // Todo: Handle formatDate
            '$entity.due_date' => optional($this->entity)->due_date, // Todo: Handle formatDate
            '$entity.last_sent_date' => optional($this->entity)->last_sent_date,
            '$entity.public_notes' => optional($this->entity)->public_notes,
            '$entity.terms' => optional($this->entity)->terms,
            '$entity.amount' => optional($this->entity)->amount, // Todo: Handle moneyformat
            '$entity.balance' => optional($this->entity)->balance, // Todo: Handle moneyformat
            '$entity.created_at' => optional($this->entity)->created_at, // Todo: Handle formatDate

            '$entity.status' => optional(optional($this->entity))->badgeForStatus(optional($this->entity)->status_id), // It would be nice if we have method that will only return status as text, not with markup.
            '$entity.project' => optional(optional($this->entity)->project)->name,
            '$entity.project.date' => optional(optional($this->entity)->project)->date,

            '$invitation.id' => optional($this->invitation)->id,
            '$invitation.user.first_name' => optional(optional($this->invitation)->user)->first_name,
            '$invitation.user.last_name' => optional(optional($this->invitation)->user)->last_name,
            '$invitation.sent_date' => optional($this->invitation)->sent_date, // Todo: Handle formatDate
            '$invitation.viewed_date' => optional($this->invitation)->viewed_date, // Todo: Handle formatDate,
            '$invitation.opened_date' => optional($this->invitation)->opened_date, // Todo: Handle formatDate,
        ];
    }
}
