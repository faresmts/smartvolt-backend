<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Routine extends Model
{
    /** @use HasFactory<\Database\Factories\RoutineFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'targetable_id',
        'targetable_type',
        'action',
        'cron_expression',
        'is_active',
    ];

    /**
     * Get the user that owns the routine.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the model that the routine belongs to.
     */
    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}

