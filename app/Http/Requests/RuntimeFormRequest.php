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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait RuntimeFormRequest
{
    public static function runFormRequest($value)
    {
        $value = self::getMockedRequestByParameters($value);

        $validator = self::createFrom($value, new static());

        $validator->setContainer(app());

        $validator->prepareForValidation();

        $validator->setValidator(Validator::make($validator->all(), $validator->rules()));

        $instance = $validator->getValidatorInstance();

        return $instance;
    }

    protected static function getMockedRequestByParameters($paramters)
    {
        $mockRequest = Request::create('', 'POST');

        $mockRequest->merge($paramters);

        return $mockRequest;
    }
}
