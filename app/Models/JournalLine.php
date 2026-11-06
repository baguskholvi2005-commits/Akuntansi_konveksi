<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $guarded = [];

    public function journal() { return $this->belongsTo(Journal::class); }
    public function account() { return $this->belongsTo(Account::class); }
}