<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests;

use App\Http\ValidationRules\User\RelatedUserRule;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Request extends FormRequest
{
    use MakesHash;
    use RuntimeFormRequest;

    private const GLOBAL_RULE_METHODS = [
        'assigned_user_id' => true,
        'invoice_id' => true,
        'vendor_id' => true,
        'tags' => true,
    ];

    /** @var class-string|null */
    protected ?string $tag_entity_type = null;

    protected $file_validation = 'sometimes|file|max:100000|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx,webp,xml,zip,csv,ods,odt,odp,txt';
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    public function fileValidation()
    {
        if (config('ninja.upload_extensions')) {
            return $this->file_validation . "," . config('ninja.upload_extensions');
        }

        return $this->file_validation;

    }

    public function globalRules(array $rules): array
    {
        $merge_rules = $rules;

        foreach ($this->all() as $key => $value) {

            if ($key == 'user') {
                continue;
            }

            if (isset(self::GLOBAL_RULE_METHODS[$key])) {
                $merge_rules = $this->{$key}($merge_rules);
            }
        }

        return $merge_rules;
    }

    private function assigned_user_id($rules)
    {
        $rules['assigned_user_id'] = [
            'bail',
            'sometimes',
            'nullable',
            new RelatedUserRule($this->all()),
        ];

        return $rules;
    }

    private function invoice_id($rules)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules['invoice_id'] = 'bail|nullable|sometimes|exists:invoices,id,company_id,' . $user->company()->id . ',client_id,' . $this['client_id'];

        return $rules;
    }

    private function vendor_id($rules)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules['vendor_id'] = 'bail|nullable|sometimes|exists:vendors,id,company_id,' . $user->company()->id;

        return $rules;
    }

    private function tags(array $rules): array
    {
        if (! $this->tag_entity_type) {
            return $rules;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return array_merge($rules, $this->tagRules($user->company()->id, $this->tag_entity_type));
    }

    public function decodePrimaryKeys($input)
    {
        foreach([
            'group_settings_id', 
            'group_id', 
            'subscription_id', 
            'assigned_user_id', 
            'user_id', 
            'vendor_id', 
            'location_id', 
            'client_id', 
            'invoice_id', 
            'expense_id', 
            'design_id', 
            'project_id', 
            'company_gateway_id', 
            'transaction_id', 
            'category_id'] as $field) {
            if (array_key_exists($field, $input) && is_string($input[$field])) {
                $input[$field] = $this->decodePrimaryKey($input[$field]);
            }
        }

        if (isset($input['client_contacts'])) {
            foreach ($input['client_contacts'] as $key => $contact) {
                if (! array_key_exists('send_email', $contact) || ! array_key_exists('id', $contact)) {
                    unset($input['client_contacts'][$key]);
                }
            }
        }

        if (isset($input['invitations']) && is_array($input['invitations'])) {
            foreach ($input['invitations'] as $key => $value) {
                if (isset($input['invitations'][$key]['id']) && is_numeric($input['invitations'][$key]['id'])) {
                    unset($input['invitations'][$key]['id']);
                }

                if (isset($input['invitations'][$key]['id']) && is_string($input['invitations'][$key]['id'])) {
                    $input['invitations'][$key]['id'] = $this->decodePrimaryKey($input['invitations'][$key]['id']);
                }

                if (array_key_exists('client_contact_id', $input['invitations'][$key]) && is_string($input['invitations'][$key]['client_contact_id'])) {
                    $input['invitations'][$key]['client_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['client_contact_id']);
                }

                if (array_key_exists('vendor_contact_id', $input['invitations'][$key]) && is_string($input['invitations'][$key]['vendor_contact_id'])) {
                    $input['invitations'][$key]['vendor_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['vendor_contact_id']);
                }
            }
        }

        if (isset($input['contacts']) && is_array($input['contacts'])) {
            foreach ($input['contacts'] as $key => $contact) {
                if (! is_array($contact)) {
                    continue;
                }

                if (array_key_exists('id', $contact) && is_numeric($contact['id'])) {
                    unset($input['contacts'][$key]['id']);
                } elseif (array_key_exists('id', $contact) && is_string($contact['id'])) {
                    $input['contacts'][$key]['id'] = $this->decodePrimaryKey($contact['id']);
                }

                //Filter the client contact password - if it is sent with ***** we should ignore it!
                if (isset($contact['password']) && is_string($contact['password'])) {
                    if (strlen($contact['password']) == 0) {
                        $input['contacts'][$key]['password'] = '';
                    } else {
                        $contact['password'] = str_replace('*', '', $contact['password']);

                        if (strlen($contact['password']) == 0) {
                            unset($input['contacts'][$key]['password']);
                        }
                    }
                }

                if (array_key_exists('email', $contact)) {
                    $input['contacts'][$key]['email'] = trim($contact['email'] ?? '');
                }
            }
        }

        foreach (['public_notes', 'footer', 'terms', 'private_notes'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = \App\Services\Pdf\Purify::clean($input[$field], true);
            }
        }

        $input = $this->normalizeTagPayload($input);

        return $input;
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeTagPayload(array $input): array
    {
        if (! array_key_exists('tags', $input) || ! is_array($input['tags'])) {
            return $input;
        }

        if (! array_is_list($input['tags'])) {
            $input['tags'] = [null];

            return $input;
        }

        $tag_ids = [];

        foreach ($input['tags'] as $tag) {
            $tag_ids[] = $this->normalizeTagId($tag);
        }

        $input['tags'] = array_values(array_unique($tag_ids));

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    protected function tagRules(int $company_id, string $entity_type): array
    {
        return [
            'tags' => ['sometimes', 'array', 'list'],
            'tags.*' => [
                'bail',
                'required',
                'integer',
                Rule::exists('tags', 'id')
                    ->where('company_id', $company_id)
                    ->where('entity_type', $entity_type)
                    ->where('is_deleted', false),
            ],
        ];
    }

    private function normalizeTagId(mixed $tag): ?int
    {
        if (is_array($tag) && array_key_exists('id', $tag)) {
            return $this->decodeTagId($tag['id']);
        }

        if (is_string($tag)) {
            return $this->decodeTagId($tag);
        }

        return null;
    }

    private function decodeTagId(mixed $tag_id): ?int
    {
        if (! is_string($tag_id) || $tag_id === '') {
            return null;
        }

        $decoded = $this->decodePrimaryKey($tag_id);

        if (is_int($decoded) && $decoded > 0) {
            return $decoded;
        }

        if (is_string($decoded) && ctype_digit($decoded) && (int) $decoded > 0) {
            return (int) $decoded;
        }

        return null;
    }

    public function prepareForValidation() {}

    /**
     * Convert to boolean
     *
     * @param $bool
     * @return bool
     */
    public function toBoolean($bool): bool
    {
        return filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }

    public function checkTimeLog(array $log): bool
    {
        if (count($log) == 0) {
            return true;
        }

        /*Get first value of all arrays*/
        $result = array_column($log, 0);

        /*Sort the array in ascending order*/
        asort($result);

        $new_array = [];

        /*Rebuild the array in order*/
        foreach ($result as $key => $value) {
            $new_array[] = $log[$key];
        }

        /*Iterate through the array and perform checks*/
        foreach ($new_array as $key => $array) {
            /*Flag which helps us know if there is a NEXT timelog*/
            $next = false;
            /* If there are more than 1 time log in the array, ensure the last timestamp is not zero*/
            if (count($new_array) > 1 && $array[1] == 0) {
                return false;
            }

            /* Check if the start time is greater than the end time */
            /* Ignore the last value for now, we'll do a separate check for this */
            if ($array[0] > $array[1] && $array[1] != 0) {
                return false;
            }

            /* Find the next time log value - if it exists */
            if (array_key_exists($key + 1, $new_array)) {
                $next = $new_array[$key + 1];
            }

            /* check the next time log and ensure the start time is GREATER than the end time of the previous record */
            if ($next && $next[0] < $array[1]) {
                return false;
            }

            /* Get the last row of the timelog*/
            $last_row = end($new_array);

            /*If the last value is NOT zero, ensure start time is not GREATER than the endtime */
            if ($last_row[1] != 0 && $last_row[0] > $last_row[1]) {
                return false;
            }

            return true;
        }

        return true;
    }
}
