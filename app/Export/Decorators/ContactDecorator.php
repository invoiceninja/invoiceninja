<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\Decorators;

use App\Models\ClientContact;

class ContactDecorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $contact = false;

        if ($entity instanceof ClientContact) {
            $contact = $entity;
        } elseif ($entity->contacts) {
            $contact = $entity->contacts()->orderBy('is_primary', 'desc')->first();
        } elseif ($entity->client) {
            $contact = $entity->client->primary_contact->first() ?? $entity->client->contacts()->whereNotNull('email')->orderBy('is_primary', 'desc')->first();
        } elseif ($entity->vendor) {
            $contact = $entity->vendor->primary_contact->first() ?? $entity->vendor->contacts()->whereNotNull('email')->orderBy('is_primary', 'desc')->first();
        }


        if ($contact && method_exists($this, $key)) {
            return $this->{$key}($contact);
        } elseif ($contact && ($contact->{$key} ?? false)) {
            return $contact->{$key};
        }

        return '';

    }

}
