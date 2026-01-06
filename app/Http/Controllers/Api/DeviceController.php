<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Device::class, 'device');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return auth()->user()->devices;
    }

    /**
     * Link a device to the authenticated user.
     */
    public function link(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|exists:devices,key',
        ]);

        $device = Device::where('key', $validated['key'])->firstOrFail();

        if ($device->user_id) {
            return response()->json(['message' => 'Device is already linked.'], 422);
        }

        $device->update(['user_id' => auth()->id()]);

        return $device;
    }

    /**
     * Display the specified resource.
     */
    public function show(Device $device)
    {
        return $device;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('groups', 'id')->where(function ($query) {
                    $query->where('user_id', auth()->id());
                }),
            ],
        ]);

        $device->update($validated);

        return $device;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Device $device)
    {
        $device->delete();

        return response()->noContent();
    }
}
