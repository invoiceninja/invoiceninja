<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentRefundFailed extends Exception
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
     * @return Response
     */
    public function render($request)
    {

        // $msg = 'Unable to refund the transaction';
        $msg = ctrans('texts.warning_local_refund');

        if ($this->getMessage() && strlen($this->getMessage()) >= 1) {
            $msg = $this->getMessage();
        }

        return response()->json([
            'message' => $msg,
        ], 401);
    }
}
