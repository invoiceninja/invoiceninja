<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
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

        if($entity instanceof ClientContact) {
            $contact = $entity;
        } elseif($entity->contacts) {
            $contact = $entity->contacts()->first();
        }

        if($contact && method_exists($this, $key)) {
            return $this->{$key}($contact);
        }

        return '';

    }

    public function phone(ClientContact $contact) {
        return $contact->phone ?? '';
    }
    public function first_name(ClientContact $contact) {
        return $contact->first_name ?? '';
    }
    public function last_name(ClientContact $contact) {
        return $contact->last_name ?? '';
    }
    public function email(ClientContact $contact) {
        return $contact->email ?? '';
    }
    public function custom_value1(ClientContact $contact) {
        return $contact->custom_value1 ?? '';
    }
    public function custom_value2(ClientContact $contact) {
        return $contact->custom_value2 ?? '';
    }
    public function custom_value3(ClientContact $contact) {
        return $contact->custom_value3 ?? '';
    }
    public function custom_value4(ClientContact $contact) {
        return $contact->custom_value4 ?? '';
    }

}
