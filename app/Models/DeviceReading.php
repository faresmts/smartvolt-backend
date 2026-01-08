<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceReading extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceReadingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'device_id',
        'is_on',
        'voltage',
        'consumption',
    ];

    /**
     * Get the device that owns the reading.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
