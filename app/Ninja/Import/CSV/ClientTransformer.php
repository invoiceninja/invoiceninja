<?php

namespace App\Ninja\Import\CSV;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class ClientTransformer.
 */
class ClientTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if (isset($data->name) && $this->hasClient($data->name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
				//'currency_id' => isset($data->currency) ? $this->getCurrencyId($data->currency) : null,
				'created_at' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
				'updated_at' => isset($data->updated_at) ? date('Y-m-d', strtotime($data->updated_at)) : null,
				'deleted_at' => isset($data->deleted_at) ? date('Y-m-d', strtotime($data->deleted_at)) : null,
                'name' => $this->getString($data, 'name'),
				'address1' => $this->getString($data, 'address1'),
                'address2' => $this->getString($data, 'address2'),
                'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'state'),
                'postal_code' => $this->getString($data, 'postal_code'),
				'country_id' => isset($data->country) ? $this->getCountryId($data->country) : null,
				'work_phone' => $this->getString($data, 'work_phone'),
				'private_notes' => $this->getString($data, 'private_notes'),
				'balance' => $this->getFloat($data, 'balance'),
				'paid_to_date' => $this->getFloat($data, 'paid_to_date'),
				'last_login' => isset($data->last_login) ? date('Y-m-d', strtotime($data->last_login)) : null,
				'website' => $this->getString($data, 'website'),
				//'industry_id' => isset($data->industry) ? $this->getIndustryId($data->industry) : null,
				//'size_id' => isset($data->size) ? $this->getSizeId($data->size) : null,
				'is_deleted' => $clientId ? false : true,
				//'payment_terms' => $this->getNumber($data, 'payment_terms'),
				//'public_id' => $this->getNumber($data, 'public_id'),
                'custom_value1' => $this->getString($data, 'custom1'),
                'custom_value2' => $this->getString($data, 'custom2'),
                'vat_number' => $this->getString($data, 'vat_number'),
                'id_number' => $this->getString($data, 'id_number'),
				'language_id' => $this->getString($data, 'language_id'),
				//'invoice_number_counter
				//'quote_number_counter
                'public_notes' => $this->getString($data, 'public_notes'),
				//credit_number_counter
				//task_rate
				'shipping_address1' => $this->getString($data, 'shipping_address1'),
				'shipping_address2' => $this->getString($data, 'shipping_address2'),
				'shipping_city' => $this->getString($data, 'shipping_city'),
				'shipping_state' => $this->getString($data, 'shipping_state'),
				'shipping_postal_code' => $this->getString($data, 'shipping_postal_code'),
				'shipping_country_id' => $this->getNumber($data, 'shipping_country'),
				//'show_tasks_in_portal' =>
				//'send_reminders' =>
                'contacts' => [
                    [
						//'client_id' => $clientId,
						'created_at' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
						'updated_at' => isset($data->updated_at) ? date('Y-m-d', strtotime($data->updated_at)) : null,
						'deleted_at' => isset($data->deleted_at) ? date('Y-m-d', strtotime($data->deleted_at)) : null,
                        'first_name' => $this->getString($data, 'contact_first_name'),
                        'last_name' => $this->getString($data, 'contact_last_name'),
                        'email' => $this->getString($data, 'contact_email'),
                        'phone' => $this->getString($data, 'contact_phone'),
						'last_login' => isset($data->last_login) ? date('Y-m-d', strtotime($data->last_login)) : null,
						'password' => $this->getString($data, 'password'),
                        'custom_value1' => $this->getString($data, 'contact_custom1'),
                        'custom_value2' => $this->getString($data, 'contact_custom2'),
                    ],
                ],
            ];
        });
    }
}
