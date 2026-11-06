<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    public const STAGES = [
        'potong' => 'Potong',
        'sablon' => 'Sablon / Bordir',
        'jahit'  => 'Jahit',
        'qc'     => 'QC dan Packing',
    ];

    public function getProgressAttribute(): int
    {
        if ($this->status === 'selesai') return 100;
        if ($this->status === 'draft')   return 0;
        $i = array_search($this->stage, array_keys(self::STAGES));

        return (int) round((($i === false ? 0 : $i) + 0.5) / count(self::STAGES) * 100);
    }

    protected $guarded = [];

    public function item()      { return $this->belongsTo(Item::class); }
    public function materials() { return $this->hasMany(ProductionMaterial::class); }
        public function payments()  { return $this->hasMany(ProductionPayment::class); }
}
