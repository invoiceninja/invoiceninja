<?php namespace app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Validation\Factory;

class CreateVendorRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'vendorcontacts' => 'valid_contacts',
        ];
    }

    public function validator($factory)
    {
        // support submiting the form with a single contact record
        $input = $this->input();
        if (isset($input['vendor_contact'])) {
            $input['vendor_contacts'] = [$input['vendor_contact']];
            unset($input['vendor_contact']);
            $this->replace($input);
        }

        return $factory->make(
            $this->input(), $this->container->call([$this, 'rules']), $this->messages()
        );
    }
}
