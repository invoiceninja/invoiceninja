<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomizePrice extends BaseModel
{
    use HasFactory;
    use MakesHash;

    protected $fillable = [
        'client_id',
        'product_id',
        'price',
    ];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'price' => 'float'
    ];

    protected $hidden = [
        'id',
        'client_id',
        'hashed_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
