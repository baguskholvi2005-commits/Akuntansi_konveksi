<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $guarded = [];

    public function material() { return $this->belongsTo(Item::class, 'material_id'); }
}