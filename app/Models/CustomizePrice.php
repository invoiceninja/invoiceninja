<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomizePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'product_id',
        'price',
    ];

    public function product(){
        return $this->belongsTo(Product::class);
    }

    public function client(){
        return $this->belongsTo(Client::class);
    }
}
