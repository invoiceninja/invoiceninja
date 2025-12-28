<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Enum;

enum HttpVerb: string
{
    case POST = 'post';
    case PUT = 'put';
    case GET = 'get';
    case PATCH = 'patch';
    case DELETE = 'delete';
}
