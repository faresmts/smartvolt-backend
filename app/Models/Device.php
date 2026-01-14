<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'user_id',
        'group_id',
        'key',
        'ip_address',
        'type',
        'is_on',
        'voltage',
        'consumption',
    ];

    /**
     * Get the user that owns the device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the group that the device belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get all of the device's usage goals.
     */
    public function usageGoals(): MorphMany
    {
        return $this->morphMany(UsageGoal::class, 'goalable');
    }

    /**
     * Get all of the device's routines.
     */
    public function routines(): MorphMany
    {
        return $this->morphMany(Routine::class, 'targetable');
    }

    /**
     * Get the energy readings for the device.
     */
    public function energyReadings(): HasMany
    {
        return $this->hasMany(EnergyReading::class);
    }
}
