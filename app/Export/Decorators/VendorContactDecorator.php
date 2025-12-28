<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\Decorators;

use App\Models\VendorContact;

class VendorContactDecorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $contact = false;

        if ($entity instanceof VendorContact) {
            $contact = $entity;
        } elseif ($entity->contacts) {
            $contact = $entity->contacts()->first();
        }

        if ($contact && method_exists($this, $key)) {
            return $this->{$key}($contact);
        } elseif ($contact && ($contact->{$key} ?? false)) {
            return $contact->{$key} ?? '';
        }

        return '';

    }


}
