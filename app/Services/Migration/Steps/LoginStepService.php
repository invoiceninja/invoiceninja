<?php


namespace App\Services\Migration\Steps;

class LoginStepService
{
    private $request;

    private $response;

    private $successful;

    private $data;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function validate()
    {
        $rules = [
            'email' => ['required'],
            'password' => ['required'],
        ];

        if (session()->get('migration_option') == 'self_hosted') {
            $rules['x_api_secret'] = ['required'];
            $rules['self_hosted_url'] = ['required'];
        }

        return $rules;
    }

    public function start()
    {
        $this->data = (object) $this->request->all();

        if (session()->get('migration_option') == 'self_hosted') {
            return $this->loginSelfHosted();
        }

        return $this->loginHosted();
    }

    public function getSuccessful()
    {
        return $this->successful;
    }

    public function onSuccess()
    {
        return '/migration/steps/settings';
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function onFailure()
    {
        return '/migration/steps/login';
    }

    private function loginSelfHosted()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-API-SECRET' => $this->data->x_api_secret,
        ];

        $credentials = [
            'email' => $this->data->email,
            'password' => $this->data->password,
        ];

        $response = \Unirest\Request::post(
            $this->data->self_hosted_url . '/api/v1/login?include=token',
            $headers,
            json_encode($credentials)
        );

        if(in_array($response->code, [401, 422, 500])) {
            $this->successful = false;
        }

        if($response->code == 200) {
            $this->successful = true;

            session()->put('X_API_SECRET', $this->data->x_api_secret);
            session()->put('X_API_TOKEN', $response->body->data[0]->token->token);
            session()->put('SELF_HOSTED_URL', $this->data->self_hosted_url);
        }

        $this->response = [
            'code' => $response->code,
            'type' => 'single',
            'content' => $this->successful ? 'You authenticated successfully!' : $response->body->message,
        ];
    }

    public function loginHosted()
    {
        // ..
    }
}
