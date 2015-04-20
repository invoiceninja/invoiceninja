<?php namespace App\Models;

use Eloquent;

class InvoiceDesign extends Eloquent
{
    public $timestamps = false;
    
    public function scopeAvailableDesigns($query) {
      $designs = $query->where('id', '<=', \Auth::user()->maxInvoiceDesignId())->orderBy('id')->get();
       
      foreach($designs as $design) {
        if($design->filename) {
          $fileName = public_path(strtolower("js/templates/{$design->name}.js"));
          if(file_exists($fileName)) {
            $design->javascript = file_get_contents($fileName);
          }
        }
      }
      
      return $designs;
    }
}
