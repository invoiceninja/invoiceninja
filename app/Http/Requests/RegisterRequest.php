<?php namespace app\Http\Requests;

use Auth;
use App\Http\Requests\Request;
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

    public function __construct(\Illuminate\Http\Request $request)
    {
        $this->request = $request;
    }

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

        Log::info($this->request->api_secret);
        Log::info($this->request->email);
        
        if(!isset($this->request->api_secret))
            return parent::response($errors);

        Log::info($errors);

        foreach($errors as $err) {
            foreach ($err as $key => $value) {

                Log::info($err);
                Log::info($key);
                Log::info($value);

                $error['error'] = ['message'=>$value];
                $error = json_encode($error, JSON_PRETTY_PRINT);
                $headers = Utils::getApiHeaders();

                return Response::make($error, 400, $headers);
            }
        }
    }

}
