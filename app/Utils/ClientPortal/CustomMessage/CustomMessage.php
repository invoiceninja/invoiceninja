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
            '$company.id' => $this->company?->id,
            '$company.name' => $this->company?->getSetting('name'),
            '$company.website' => $this->company?->getSetting('website'),

            '$client.id' => $this->client?->id,
            '$client.name' => $this->client?->name,
            '$client.website' => $this->client?->website,
            '$client.public_notes' => $this->client?->public_notes,
            '$client.phone' => $this->client?->phone,
            '$client.balance' => $this->client?->balance,
            '$client.address1' => $this->client?->present()?->address1,
            '$client.address2' => $this->client?->present()?->address2,
            '$client.city' => $this->client?->present()?->city,
            '$client.state' => $this->client?->present()?->state,
            '$client.postal_code' => $this->client?->present()?->postal_code,
            '$client.country' => $this->client?->present()?->country?->full_name,
            '$client.address' => $this->client?->present()?->address(),
            '$client.shipping_address' => $this->client?->present()?->shipping_address(),
            '$client.primary_contact_name' => $this->client?->present()?->primary_contact_name(),
            '$client.city_state' => $this->client?->present()?->getCityState(),

            '$contact.first_name' => $this->contact?->first_name,
            '$contact.last_name' => $this->contact?->last_name,
            '$contact.phone' => $this->contact?->phone,
            '$contact.email' => $this->contact?->email,
            '$contact.avatar' => $this->contact?->avatar,

            '$group.id' => $this->group?->id,

            '$entity.id' => $this->entity?->hashed_id,
            '$entity.number' => $this->entity?->number,
            '$entity.discount' => $this->entity?->discount,
            '$entity.date' => $this->entity?->date, // Todo: Handle formatDate
            '$entity.due_date' => $this->entity?->due_date, // Todo: Handle formatDate
            '$entity.last_sent_date' => $this->entity?->last_sent_date,
            '$entity.public_notes' => $this->entity?->public_notes,
            '$entity.terms' => $this->entity?->terms,
            '$entity.amount' => $this->entity?->amount, // Todo: Handle moneyformat
            '$entity.balance' => $this->entity?->balance, // Todo: Handle moneyformat
            '$entity.created_at' => $this->entity?->created_at, // Todo: Handle formatDate

            '$entity.status' => optional(optional($this->entity))->badgeForStatus($this->entity?->status_id), // It would be nice if we have method that will only return status as text, not with markup.
            '$entity.project' => $this->entity?->project?->name,
            '$entity.project.date' => $this->entity?->project?->date,

            '$invitation.id' => $this->invitation?->id,
            '$invitation.user.first_name' => $this->invitation?->user?->first_name,
            '$invitation.user.last_name' => $this->invitation?->user?->last_name,
            '$invitation.sent_date' => $this->invitation?->sent_date, // Todo: Handle formatDate
            '$invitation.viewed_date' => $this->invitation?->viewed_date, // Todo: Handle formatDate,
            '$invitation.opened_date' => $this->invitation?->opened_date, // Todo: Handle formatDate,
        ];
    }
}
