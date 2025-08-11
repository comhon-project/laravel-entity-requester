<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Purchase extends Model
{
    public function buyer(): MorphTo
    {
        return $this->morphTo();
    }
}
