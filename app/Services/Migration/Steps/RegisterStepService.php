<?php


namespace App\Services\Migration\Steps;

class RegisterStepService
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
            'first_name' => ['required'],
            'last_name' => ['required'],
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
        $this->data = (object)$this->request->all();

        if (session()->get('migration_option') == 'self_hosted') {
            return $this->registerSelfHosted();
        }

        return $this->registerHosted();
    }

    public function getSuccessful()
    {
        return $this->successful;
    }

    public function onSuccess()
    {
        return '/migration/steps/clients';
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function onFailure()
    {
        return '/migration/steps/register';
    }

    private function registerSelfHosted()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-API-SECRET' => $this->data->x_api_secret,
        ];

        $credentials = [
            'first_name' => $this->data->first_name,
            'last_name' => $this->data->last_name,
            'email' => $this->data->email,
            'password' => $this->data->password,
            'terms_of_service' => 1,
            'privacy_policy' => 1,
        ];

        $response = \Unirest\Request::post(
            $this->data->self_hosted_url . '/api/v1/signup?include=token',
            $headers,
            json_encode($credentials)
        );

        if (in_array($response->code, [401, 422, 500])) {
            $this->successful = false;
        }

        if ($response->code == 200) {
            $this->successful = true;

            session()->put('X_API_SECRET', $this->data->x_api_secret);
            session()->put('X_API_TOKEN', $response->body->data[0]->token->token);
        }

        $this->response = [
            'code' => $response->code,
            'type' => $this->successful ? 'single' : 'array',
            'content' => $this->successful ? 'Account created successfully!' : ($response->body->message) ?? null,
        ];
    }

    public function registerHosted()
    {
        // ..
    }
}
