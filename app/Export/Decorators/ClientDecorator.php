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

use App\Models\Client;

class ClientDecorator extends Decorator implements DecoratorInterface
{
    private $entity_key = 'client';

    public function transform(string $key, mixed $entity): mixed
    {
        $client = false;

        if($entity instanceof Client){
            $client = $entity;
        }
        elseif($entity->client) {
            $client = $entity->client;
        }

        if($client && method_exists($this, $key)) {
            return $this->{$key}($client);
        }

        return '';
    }

        public function name(Client $client) {
            return $client->present()->name();
        }
        public function number(Client $client) {
            return $client->number ?? '';
        }
        public function user(Client $client) {
            return $client->user->present()->name();
        }
        public function assigned_user(Client $client) {
            return $client->assigned_user ? $client->user->present()->name() : '';
        }
        public function balance(Client $client) {
            return $client->balance ?? 0;
        }
        public function paid_to_date(Client $client) {
            return $client->paid_to_date ?? 0;
        }
        public function currency_id(Client $client) {
            return $client->currency() ? $client->currency()->code : $client->company->currency()->code;
        }
        public function website(Client $client) {
            return $client->website ?? '';
        }
        public function private_notes(Client $client) {
            return $client->private_notes ?? '';
        }
        public function industry_id(Client $client) {
            return $client->industry ? ctrans("texts.industry_{$client->industry->name}") : '';
        }
        public function size_id(Client $client) {
            return $client->size ? ctrans("texts.size_{$client->size->name}") : '';
        }
        public function phone(Client $client) {
            return $client->phone ?? '';
        }
        public function address1(Client $client) {
            return $client->address1 ?? '';
        }
        public function address2(Client $client) {
            return $client->address2 ?? '';
        }
        public function city(Client $client) {
            return $client->city ?? '';
        }
        public function state(Client $client) {
            return $client->state ?? '';
        }
        public function postal_code(Client $client) {
            return $client->postal_code ?? '';
        }
        public function country_id(Client $client) {
            return $client->country ? ctrans("texts.country_{$client->country->name}") : '';
        }
        public function shipping_address1(Client $client) {
            return $client->shipping_address1 ?? '';
        }
        public function shipping_address2(Client $client) {
            return $client->shipping_address2 ?? '';
        }
        public function shipping_city(Client $client) {
            return $client->shipping_city ?? '';
        }
        public function shipping_state(Client $client) {
            return $client->shipping_state ?? '';
        }
        public function shipping_postal_code(Client $client) {
            return $client->shipping_postal_code ?? '';
        }
        public function shipping_country_id(Client $client) {
            return $client->shipping_country ? ctrans("texts.country_{$client->shipping_country->name}") : '';
        }
        public function payment_terms(Client $client) {
            return $client?->settings?->payment_terms ?? $client->company->settings->payment_terms;
        }
        public function vat_number(Client $client) {
            return $client->vat_number ?? '';
        }
        public function id_number(Client $client) {
            return $client->id_number ?? '';
        }
        public function public_notes(Client $client) {
            return $client->public_notes ?? '';
        }
        public function custom_value1(Client $client) {
            return $client->custom_value1 ?? '';
        }
        public function custom_value2(Client $client) {
            return $client->custom_value2 ?? '';
        }
        public function custom_value3(Client $client) {
            return $client->custom_value3 ?? '';
        }
        public function custom_value4(Client $client) {
            return $client->custom_value4 ?? '';
        }
        public function payment_balance(Client $client) {
            return $client->payment_balance ?? 0;
        }
        public function credit_balance(Client $client) {
            return $client->credit_balance ?? 0;
        }
        public function classification(Client $client) {
            ctrans("texts.{$client->classification}") ?? '';
        }

        public function status(Client $client)
        {
            if ($client->is_deleted) {
                return ctrans('texts.deleted');
            }

            if ($client->deleted_at) {
                return ctrans('texts.archived');
            }

            return ctrans('texts.active');
        }




}
