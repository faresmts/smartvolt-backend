<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsageGoal;
use Illuminate\Http\Request;

class UsageGoalController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(UsageGoal::class, 'usage_goal');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return auth()->user()->usageGoals;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'goalable_id' => 'required|integer',
            'goalable_type' => 'required|string|in:App\Models\Device,App\Models\Group',
            'target_kwh' => 'required|numeric|min:0',
            'period' => 'required|string|in:daily,weekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $goalable = $validated['goalable_type']::findOrFail($validated['goalable_id']);
        if ($goalable->user_id !== auth()->id()) {
            abort(403, 'You do not own this resource.');
        }

        $usageGoal = UsageGoal::create([
            'user_id' => auth()->id(),
            'goalable_id' => $validated['goalable_id'],
            'goalable_type' => $validated['goalable_type'],
            'target_kwh' => $validated['target_kwh'],
            'period' => $validated['period'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return $usageGoal;
    }

    /**
     * Display the specified resource.
     */
    public function show(UsageGoal $usageGoal)
    {
        return $usageGoal;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UsageGoal $usageGoal)
    {
        $validated = $request->validate([
            'target_kwh' => 'sometimes|required|numeric|min:0',
            'period' => 'sometimes|required|string|in:daily,weekly,monthly',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
        ]);

        $usageGoal->update($validated);

        return $usageGoal;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UsageGoal $usageGoal)
    {
        $usageGoal->delete();

        return response()->noContent();
    }
}
