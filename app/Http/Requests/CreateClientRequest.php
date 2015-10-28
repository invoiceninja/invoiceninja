<?php namespace app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Validation\Factory;

class CreateClientRequest extends Request
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
            'contacts' => 'valid_contacts',
        ];
    }

    public function validator($factory)
    {
        // support submiting the form with a single client record
        $input = $this->input();
        if (isset($input['contact'])) {
            $input['contacts'] = [$input['contact']];
            unset($input['contact']);
            $this->replace($input);
        }

        return $factory->make(
            $this->input(), $this->container->call([$this, 'rules']), $this->messages()
        );
    }
}
