<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\UsageGoal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsageGoalController extends Controller
{
    // Common validation rules for goals
    private function validateGoal(Request $request)
    {
        return $request->validate([
            'target_kwh' => 'required|numeric|min:0',
            'period' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
    }

    // --- User Goal Methods ---

    /**
     * Display the user's general usage goal.
     */
    public function showUserGoal(Request $request)
    {
        $user = $request->user();
        return $user->usageGoal;
    }

    /**
     * Store or update the user's general usage goal.
     */
    public function storeUserGoal(Request $request)
    {
        $validated = $this->validateGoal($request);
        $user = $request->user();

        $user->usageGoal()->updateOrCreate(
            ['goalable_id' => $user->id, 'goalable_type' => get_class($user)],
            [
                'user_id' => $user->id,
                'target_kwh' => $validated['target_kwh'],
                'period' => $validated['period'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]
        );

        return $user->usageGoal;
    }

    // --- Group Goal Methods ---

    /**
     * Display the specified group's usage goal.
     */
    public function showGroupGoal(Group $group)
    {
        $this->authorize('view', $group); // Ensure user can view the group
        return $group->usageGoals()->where('user_id', auth()->id())->first();
    }

    /**
     * Store or update the specified group's usage goal.
     */
    public function storeGroupGoal(Request $request, Group $group)
    {
        $this->authorize('update', $group); // Ensure user can update the group
        $validated = $this->validateGoal($request);
        $user = $request->user();

        $group->usageGoals()->updateOrCreate(
            ['goalable_id' => $group->id, 'goalable_type' => get_class($group)],
            [
                'user_id' => $user->id,
                'target_kwh' => $validated['target_kwh'],
                'period' => $validated['period'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]
        );

        return $group->usageGoals()->where('user_id', auth()->id())->first();
    }
}
