<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnergyReading extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'device_id',
        'kwh_consumption',
        'relay_status',
        'voltage_rms',
        'current_rms',
        'power',
        'energy',
        'cost',
        'recorded_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'relay_status' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the device that owns the energy reading.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
