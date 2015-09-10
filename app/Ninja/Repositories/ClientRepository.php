<?php namespace App\Ninja\Repositories;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Activity;

class ClientRepository
{
    public function find($filter = null)
    {
        $query = \DB::table('clients')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('clients.account_id', '=', \Auth::user()->account_id)
                    ->where('contacts.is_primary', '=', true)
                    ->where('contacts.deleted_at', '=', null)
                    ->select('clients.public_id', 'clients.name', 'contacts.first_name', 'contacts.last_name', 'clients.balance', 'clients.last_login', 'clients.created_at', 'clients.work_phone', 'contacts.email', 'clients.currency_id', 'clients.deleted_at', 'clients.is_deleted');

        if (!\Session::get('show_trash:client')) {
            $query->where('clients.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function getErrors($data)
    {
        $contact = isset($data['contacts']) ? (array) $data['contacts'][0] : (isset($data['contact']) ? $data['contact'] : []);
        $validator = \Validator::make($contact, [
            'email' => 'email|required_without:first_name',
            'first_name' => 'required_without:email',
        ]);
        if ($validator->fails()) {
            return $validator->messages();
        }

        return false;
    }

    public function save($publicId, $data, $notify = true)
    {
        if (!$publicId || $publicId == "-1") {
            $client = Client::createNew();
            $contact = Contact::createNew();
            $contact->is_primary = true;
            $contact->send_invoice = true;
        } else {
            $client = Client::scope($publicId)->with('contacts')->firstOrFail();
            $contact = $client->contacts()->where('is_primary', '=', true)->firstOrFail();
        }

        if (isset($data['name'])) {
            $client->name = trim($data['name']);
        }
        if (isset($data['id_number'])) {
            $client->id_number = trim($data['id_number']);
        }
        if (isset($data['vat_number'])) {
            $client->vat_number = trim($data['vat_number']);
        }
        if (isset($data['work_phone'])) {
            $client->work_phone = trim($data['work_phone']);
        }
        if (isset($data['custom_value1'])) {
            $client->custom_value1 = trim($data['custom_value1']);
        }
        if (isset($data['custom_value2'])) {
            $client->custom_value2 = trim($data['custom_value2']);
        }
        if (isset($data['address1'])) {
            $client->address1 = trim($data['address1']);
        }
        if (isset($data['address2'])) {
            $client->address2 = trim($data['address2']);
        }
        if (isset($data['city'])) {
            $client->city = trim($data['city']);
        }
        if (isset($data['state'])) {
            $client->state = trim($data['state']);
        }
        if (isset($data['postal_code'])) {
            $client->postal_code = trim($data['postal_code']);
        }
        if (isset($data['country_id'])) {
            $client->country_id = $data['country_id'] ? $data['country_id'] : null;
        }
        if (isset($data['private_notes'])) {
            $client->private_notes = trim($data['private_notes']);
        }
        if (isset($data['size_id'])) {
            $client->size_id = $data['size_id'] ? $data['size_id'] : null;
        }
        if (isset($data['industry_id'])) {
            $client->industry_id = $data['industry_id'] ? $data['industry_id'] : null;
        }
        if (isset($data['currency_id'])) {
            $client->currency_id = $data['currency_id'] ? $data['currency_id'] : null;
        }
        if (isset($data['language_id'])) {
            $client->language_id = $data['language_id'] ? $data['language_id'] : null;
        }
        if (isset($data['payment_terms'])) {
            $client->payment_terms = $data['payment_terms'];
        }
        if (isset($data['website'])) {
            $client->website = trim($data['website']);
        }

        $client->save();

        $isPrimary = true;
        $contactIds = [];

        if (isset($data['contact'])) {
            $info = $data['contact'];
            if (isset($info['email'])) {
                $contact->email = trim($info['email']);
            }
            if (isset($info['first_name'])) {
                $contact->first_name = trim($info['first_name']);
            }
            if (isset($info['last_name'])) {
                $contact->last_name = trim($info['last_name']);
            }
            if (isset($info['phone'])) {
                $contact->phone = trim($info['phone']);
            }
            $contact->is_primary = true;
            $contact->send_invoice = true;
            $client->contacts()->save($contact);
        } else {
            foreach ($data['contacts'] as $record) {
                $record = (array) $record;

                if ($publicId != "-1" && isset($record['public_id']) && $record['public_id']) {
                    $contact = Contact::scope($record['public_id'])->firstOrFail();
                } else {
                    $contact = Contact::createNew();
                }

                if (isset($record['email'])) {
                    $contact->email = trim($record['email']);
                }
                if (isset($record['first_name'])) {
                    $contact->first_name = trim($record['first_name']);
                }
                if (isset($record['last_name'])) {
                    $contact->last_name = trim($record['last_name']);
                }
                if (isset($record['phone'])) {
                    $contact->phone = trim($record['phone']);
                }
                $contact->is_primary = $isPrimary;
                $contact->send_invoice = isset($record['send_invoice']) ? $record['send_invoice'] : true;
                $isPrimary = false;

                $client->contacts()->save($contact);
                $contactIds[] = $contact->public_id;
            }

            foreach ($client->contacts as $contact) {
                if (!in_array($contact->public_id, $contactIds)) {
                    $contact->delete();
                }
            }
        }

        $client->save();

        if (!$publicId || $publicId == "-1") {
            Activity::createClient($client, $notify);
        }

        return $client;
    }

    public function bulk($ids, $action)
    {
        $clients = Client::withTrashed()->scope($ids)->get();

        foreach ($clients as $client) {
            if ($action == 'restore') {
                $client->restore();

                $client->is_deleted = false;
                $client->save();
            } else {
                if ($action == 'delete') {
                    $client->is_deleted = true;
                    $client->save();
                }

                $client->delete();
            }
        }

        return count($clients);
    }
}
