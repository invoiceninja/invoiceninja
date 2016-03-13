<?php namespace app\Http\Requests;

use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Factory;
use App\Libraries\Utils;
use Response;

class RegisterRequest extends Request
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

        $rules = [
            'email' => 'required|unique:users',
            'first_name' => 'required',
            'last_name' => 'required',
            'password' => 'required',
        ];

        return $rules;
    }

    public function response(array $errors)
    {

        foreach($errors as $error) {
            foreach ($error as $key => $value) {

                $message['error'] = ['message'=>$value];
                $message = json_encode($message, JSON_PRETTY_PRINT);
                $headers = Utils::getApiHeaders();

                return Response::make($error, 400, $headers);
            }
        }
    }



}
