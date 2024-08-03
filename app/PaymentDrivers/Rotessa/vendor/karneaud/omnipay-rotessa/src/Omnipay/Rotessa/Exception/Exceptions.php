<?php 

namespace Omnipay\Rotessa\Exception;

class BadRequestException extends \Exception {
    protected $message = "Your request includes invalid parameters";
    protected $code = 400;
}

class UnauthorizedException extends \Exception {
    protected $message = "Your API key is not valid or is missing";
    protected $code = 401;
}

class NotFoundException extends \Exception {
    protected $message = "The specified resource could not be found";
    protected $code = 404;
}

class NotAcceptableException extends \Exception {
    protected $message = "You requested a format that isn’t json";
    protected $code = 406;
}

class UnprocessableEntityException extends \Exception {
    protected $message = "Your request results in invalid data";
    protected $code = 422;
}

class InternalServerErrorException extends \Exception {
    protected $message = "We had a problem with our server. Try again later";
    protected $code = 500;
}

class ServiceUnavailableException extends \Exception {
    protected $message = "We're temporarily offline for maintenance. Please try again later";
    protected $code = 503;
}

class ValidationException extends \Exception {
    protected $message = "A validation error has occured";
    protected $code = 600;
}