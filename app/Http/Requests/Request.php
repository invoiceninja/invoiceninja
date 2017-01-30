<?php

namespace App\Http\Requests;

use App\Libraries\Utils;
use Illuminate\Foundation\Http\FormRequest;
use Response;

// https://laracasts.com/discuss/channels/general-discussion/laravel-5-modify-input-before-validation/replies/34366
abstract class Request extends FormRequest
{
    // populate in subclass to auto load record
    protected $autoload = [];

    /**
     * Validate the input.
     *
     * @param \Illuminate\Validation\Factory $factory
     *
     * @return \Illuminate\Validation\Validator
     */
    public function validator($factory)
    {
        return $factory->make(
            $this->sanitizeInput(), $this->container->call([$this, 'rules']), $this->messages()
        );
    }

    /**
     * Sanitize the input.
     *
     * @return array
     */
    protected function sanitizeInput()
    {
        if (method_exists($this, 'sanitize')) {
            $input = $this->container->call([$this, 'sanitize']);
        } else {
            $input = $this->all();
        }

        // autoload referenced entities
        foreach ($this->autoload as $entityType) {
            if ($id = $this->input("{$entityType}_public_id") ?: $this->input("{$entityType}_id")) {
                $class = 'App\\Models\\' . ucwords($entityType);
                $entity = $class::scope($id)->firstOrFail();
                $input[$entityType] = $entity;
                $input[$entityType . '_id'] = $entity->id;
            }
        }

        $this->replace($input);

        return $this->all();
    }

    public function response(array $errors)
    {
        /* If the user is not validating from a mobile app - pass through parent::response */
        if (! request()->api_secret) {
            return parent::response($errors);
        }

        /* If the user is validating from a mobile app - pass through first error string and return error */
        foreach ($errors as $error) {
            foreach ($error as $key => $value) {
                $message['error'] = ['message' => $value];
                $message = json_encode($message, JSON_PRETTY_PRINT);
                $headers = Utils::getApiHeaders();

                return Response::make($message, 400, $headers);
            }
        }
    }
}
