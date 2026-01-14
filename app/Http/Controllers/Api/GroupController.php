<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Group::class, 'group');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return auth()->user()->groups()
            ->withCount('devices')
            ->withCount(['devices as devices_on_count' => function ($query) {
                $query->where('is_on', true);
            }])
            ->withSum('devices as total_consumption', 'consumption')
            ->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'device_ids' => 'sometimes|array',
            'device_ids.*' => [
                'integer',
                Rule::exists('devices', 'id')->where(function ($query) {
                    $query->where('user_id', auth()->id());
                }),
            ],
        ]);

        $group = auth()->user()->groups()->create($validated);

        if ($request->has('device_ids')) {
            Device::where('user_id', auth()->id())
                ->whereIn('id', $validated['device_ids'])
                ->update(['group_id' => $group->id]);
        }

        return $group;
    }

    /**
     * Display the specified resource.
     */
    public function show(Group $group)
    {
        return $group->load('devices:id,name,group_id,consumption,is_on')
            ->loadSum('devices as total_consumption', 'consumption');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'device_ids' => 'sometimes|array',
            'device_ids.*' => [
                'integer',
                Rule::exists('devices', 'id')->where(function ($query) {
                    $query->where('user_id', auth()->id());
                }),
            ],
        ]);

        if (isset($validated['name'])) {
            $group->update(['name' => $validated['name']]);
        }

        if ($request->has('device_ids')) {
            // Dissociate devices that are in the group but not in the request
            $group->devices()->whereNotIn('id', $validated['device_ids'])->update(['group_id' => null]);

            // Associate devices from the request
            Device::whereIn('id', $validated['device_ids'])
                ->where('user_id', auth()->id())
                ->update(['group_id' => $group->id]);
        }

        return $group;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group)
    {
        $group->delete();

        return response()->noContent();
    }

    public function unlinkDevice(Group $group, Device $device)
    {
        $this->authorize('update', $group);
        $this->authorize('update', $device);

        if ($device->group_id !== $group->id) {
            return response()->json(['message' => 'Device does not belong to this group.'], 400);
        }

        $device->update(['group_id' => null]);

        return response()->noContent();
    }
}
