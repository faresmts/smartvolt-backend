<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UsageGoal extends Model
{
    /** @use HasFactory<\Database\Factories\UsageGoalFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'goalable_id',
        'goalable_type',
        'target_kwh',
        'period',
        'name',
    ];

    /**
     * Get the user that owns the usage goal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the model that the usage goal belongs to.
     */
    public function goalable(): MorphTo
    {
        return $this->morphTo();
    }
}
