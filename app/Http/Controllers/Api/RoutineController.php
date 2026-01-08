<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Routine;
use Illuminate\Http\Request;

class RoutineController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Routine::class, 'routine');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return auth()->user()->routines;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'targetable_id' => 'required|integer',
            'targetable_type' => 'required|string|in:App\Models\Device,App\Models\Group',
            'action' => 'required|string|in:turn_on,turn_off',
            'cron_expression' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $targetable = $validated['targetable_type']::findOrFail($validated['targetable_id']);
        if ($targetable->user_id !== auth()->id()) {
            abort(403, 'You do not own this resource.');
        }

        $routine = Routine::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'targetable_id' => $validated['targetable_id'],
            'targetable_type' => $validated['targetable_type'],
            'action' => $validated['action'],
            'cron_expression' => $validated['cron_expression'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $routine;
    }

    /**
     * Display the specified resource.
     */
    public function show(Routine $routine)
    {
        return $routine;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Routine $routine)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'action' => 'sometimes|required|string|in:turn_on,turn_off',
            'cron_expression' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $routine->update($validated);

        return $routine;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Routine $routine)
    {
        $routine->delete();

        return response()->noContent();
    }
}

