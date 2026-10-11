<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['criterion', 'label', 'points', 'max_points', 'explanation'])]
class ScoreDetail extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['points' => 'integer', 'max_points' => 'integer'];
    }
}
