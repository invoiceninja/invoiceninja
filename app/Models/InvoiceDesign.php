<?php namespace app\Models;

use Eloquent;
use Auth;
use App\Models\InvoiceDesign;

class InvoiceDesign extends Eloquent
{
    public $timestamps = false;

    public function scopeAvailableDesigns($query, $utf8 = false)
    {
        $account = Auth::user()->account;
        $designs = $query->where('id', '<=', Auth::user()->maxInvoiceDesignId())->orderBy('id')->get();

        foreach ($designs as $design) {
            $fileName = public_path(strtolower("js/templates/{$design->name}.js"));
            if (($utf8 || Auth::user()->account->utf8_invoices) && file_exists($fileName)) {
                $design->javascript = file_get_contents($fileName);
            }
            
            if ($design->id == CUSTOM_DESIGN) {
                if ($account->utf8_invoices && $account->custom_design) {
                    $design->javascript = $account->custom_design;
                } else {
                    $designs->pop();
                }
            }
        }

        return $designs;
    }
}
