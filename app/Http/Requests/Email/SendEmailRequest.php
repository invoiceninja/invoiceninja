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

namespace App\Http\Requests\Email;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;

class SendEmailRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return $this->checkUserAbleToSend();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'template' => 'bail|required',
            'entity' => 'bail|required',
            'entity_id' => 'bail|required',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $settings = auth()->user()->company()->settings;

        if (empty($input['template'])) {
            $input['template'] = '';
        }

        if (! property_exists($settings, $input['template'])) {
            unset($input['template']);
        }

        if(array_key_exists('entity_id', $input))
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        
        if(array_key_exists('entity', $input))
            $input['entity'] = "App\Models\\".ucfirst(Str::camel($input['entity']));

        $this->replace($input);
    }

    public function message()
    {
        return [
            'template' => 'Invalid template.',
        ];
    }

    private function checkUserAbleToSend()
    {
        $input = $this->all();

        /*Make sure we have all the require ingredients to send a template*/
        if (array_key_exists('entity', $input) && array_key_exists('entity_id', $input) && is_string($input['entity']) && $input['entity_id']) {
            $company = auth()->user()->company();

            $entity = $input['entity'];

            /* Harvest the entity*/
            $entity_obj = $entity::whereId($input['entity_id'])->withTrashed()->company()->first();

            /* Check object, check user and company id is same as users, and check user can edit the object */
            if ($entity_obj && ($company->id == $entity_obj->company_id) && auth()->user()->can('edit', $entity_obj)) {
                return true;
            }
        }

        return false;
    }
}
