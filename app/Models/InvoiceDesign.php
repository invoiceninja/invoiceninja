<?php namespace app\Models;

use Eloquent;
use Auth;
use Cache;
use App\Models\InvoiceDesign;

class InvoiceDesign extends Eloquent
{
    public $timestamps = false;

    public static function getDesigns($forceUtf8 = false)
    {
        $account = Auth::user()->account;
        $designs = Cache::get('invoiceDesigns');
        $utf8 = $forceUtf8 || $account->utf8_invoices;

        foreach ($designs as $design) {
            if ($design->id > Auth::user()->maxInvoiceDesignId()) {
                $designs->pull($design->id);
            }

            if ($utf8) {
                $design->javascript = $design->pdfmake;
            }
            $design->pdfmake = null;

            if ($design->id == CUSTOM_DESIGN) {
                if ($utf8 && $account->custom_design) {
                    $design->javascript = $account->custom_design;
                } else {
                    $designs->pop();
                }
            }
        }
        
        return $designs;
    }
}