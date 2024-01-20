<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YodleeApiException extends Exception
{
    /**
     * Report the exception.
     *
     * @return void
     */
    public function report()
    {
        //
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function render($request)
    {
        // $msg = 'Unable to refund the transaction';
        $msg = ctrans('texts.error');

        if ($this->getMessage() && strlen($this->getMessage()) > 1) {
            $msg = $this->getMessage();
        }

        return response()->json([
            'message' => $msg,
        ], 400);
    }
}
