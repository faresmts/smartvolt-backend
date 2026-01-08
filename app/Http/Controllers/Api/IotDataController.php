<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceReading;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IotDataController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'key' => 'required|string|exists:devices,key',
            'is_on' => 'required|boolean',
            'voltage' => 'required|numeric',
            'consumption' => 'required|numeric',
        ]);

        $device = Device::where('key', $validated['key'])->firstOrFail();

        $device->update([
            'is_on' => $validated['is_on'],
            'voltage' => $validated['voltage'],
            'consumption' => $validated['consumption'],
        ]);

        DeviceReading::create([
            'device_id' => $device->id,
            'is_on' => $validated['is_on'],
            'voltage' => $validated['voltage'],
            'consumption' => $validated['consumption'],
        ]);

        return response()->noContent();
    }
}
