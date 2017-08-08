<?php

namespace App\Models;

use Auth;
use Cache;
use Eloquent;

/**
 * Class InvoiceDesign.
 */
class InvoiceDesign extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    public static $pageSizes = [
                'A0',
                'A1',
                'A2',
                'A3',
                'A4',
                'A5',
                'A6',
                'A7',
                'A8',
                'A9',
                'A10',
                'B0',
                'B1',
                'B2',
                'B3',
                'B4',
                'B5',
                'B6',
                'B7',
                'B8',
                'B9',
                'B10',
                'C0',
                'C1',
                'C2',
                'C3',
                'C4',
                'C5',
                'C6',
                'C7',
                'C8',
                'C9',
                'C10',
                'RA0',
                'RA1',
                'RA2',
                'RA3',
                'RA4',
                'SRA0',
                'SRA1',
                'SRA2',
                'SRA3',
                'SRA4',
                'Executive',
                'Folio',
                'Legal',
                'Letter',
                'Tabloid',
            ];

    /**
     * @return mixed
     */
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

            if (in_array($design->id, [CUSTOM_DESIGN1, CUSTOM_DESIGN2, CUSTOM_DESIGN3])) {
                if ($javascript = $account->getCustomDesign($design->id)) {
                    $design->javascript = $javascript;
                } else {
                    $designs->forget($design->id - 1);
                }
            }
        }

        return $designs;
    }
}
