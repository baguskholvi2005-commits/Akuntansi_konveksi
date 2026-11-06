<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    protected $guarded = [];

    public function order() { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function items() { return $this->hasMany(SalesReturnItem::class); }
}