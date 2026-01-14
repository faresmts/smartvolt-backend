<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            'name' => 'nullable|string|max:255',
            'group_id' => 'nullable|exists:groups,id',
            'ip_address' => 'nullable|string|max:45',
            'type' => ['nullable', 'string', Rule::in(['plug', 'device'])],
        ]);

        $device = Device::where('key', $validated['key'])->firstOrFail();

        if ($device->user_id) {
            return response()->json(['message' => 'Device is already linked.'], 422);
        }

        $device->update([
            'user_id' => auth()->id(),
            'name' => $validated['name'] ?? $device->name,
            'group_id' => $validated['group_id'] ?? null,
            'ip_address' => $validated['ip_address'] ?? null,
            'type' => $validated['type'] ?? 'device',
        ]);

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
            'ip_address' => 'nullable|string|max:45',
            'type' => ['nullable', 'string', Rule::in(['plug', 'device'])],
        ]);

        $device->update($validated);

        return $device;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Device $device)
    {
        $device->user_id = null;
        $device->group_id = null;
        $device->save();

        return response()->noContent();
    }

    public function toggle(Device $device)
    {
        dd($device);
        $this->authorize('update', $device);

        if (! $device->ip_address) {
            return response()->json(['message' => 'Device IP address not configured.'], 400);
        }

        $client = new Client(['base_uri' => $device->ip_address]);

        try {
            $statusResponse = $client->get('api/status');
            $statusData = json_decode($statusResponse->getBody()->getContents(), true);

            if (! isset($statusData['relay_state'])) {
                return response()->json(['message' => 'Could not determine device state.'], 500);
            }

            $currentRelayState = (bool) $statusData['relay_state'];
            $newRelayState = ! $currentRelayState;

            // 2. Send toggle command
            $toggleEndpoint = $newRelayState ? '/on' : '/off';
            $toggleResponse = $client->get($toggleEndpoint); // ESP32 uses GET for toggle

            // The ESP32 API returns a 302 redirect, so we check for successful response codes
            // Guzzle handles redirects by default, so a 200 or 302 might be considered successful
            if ($toggleResponse->getStatusCode() === 200 || $toggleResponse->getStatusCode() === 302) {
                // Update the local device state (optional, but good practice)
                $device->update(['is_on' => $newRelayState]);

                return response()->json([
                    'message' => 'Device toggled successfully.',
                    'is_on' => $newRelayState,
                ]);
            }

            return response()->json(['message' => 'Failed to toggle device.'], 500);
        } catch (RequestException $e) {
            Log::error("Failed to toggle device {$device->id} at {$device->ip_address}: ".$e->getMessage());
            return response()->json(['message' => 'Network or device communication error.'], 500);
        } catch (\Exception $e) {
            Log::error("An unexpected error occurred while toggling device {$device->id}: ".$e->getMessage());
            return response()->json(['message' => 'An internal server error occurred.'], 500);
        }
    }
}
