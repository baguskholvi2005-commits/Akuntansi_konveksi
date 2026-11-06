<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $guarded = [];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function items()    { return $this->hasMany(SalesOrderItem::class); }
    public function payments() { return $this->hasMany(SalesPayment::class); }
}