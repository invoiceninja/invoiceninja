<?php namespace App\Models;

use Eloquent;
use Auth;
use Cache;
use App\Models\InvoiceDesign;

class InvoiceDesign extends Eloquent
{
    public $timestamps = false;

    public static function getDesigns()
    {
        $account = Auth::user()->account;
        $designs = Cache::get('invoiceDesigns');

        foreach ($designs as $design) {
            if ($design->id > Auth::user()->maxInvoiceDesignId()) {
                $designs->pull($design->id);
            }
            
            $design->javascript = $design->pdfmake;
            $design->pdfmake = null;

            if ($design->id == CUSTOM_DESIGN) {
                if ($account->custom_design) {
                    $design->javascript = $account->custom_design;
                } else {
                    $designs->pop();
                }
            }
        }
        
        return $designs;
    }
}