<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Purchase extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function buyer(): MorphTo
    {
        return $this->morphTo();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopeExpensive($query)
    {
        $query->where('purchases.amount', '>=', 1000);
    }
}
