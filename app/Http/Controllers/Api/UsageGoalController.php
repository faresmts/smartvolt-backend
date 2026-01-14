<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsageGoalResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsageGoalController extends Controller
{
    // Common validation rules for goals
    private function validateGoal(Request $request)
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'target_kwh' => 'required|numeric|min:0',
            'period' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
        ]);
    }

    // --- User Goal Methods ---

    /**
     * Display the user's general usage goal.
     */
    public function showUserGoal(Request $request)
    {
        $user = $request->user();

        return new UsageGoalResource($user->usageGoal);
    }

    /**
     * Store or update the user's general usage goal.
     */
    public function storeUserGoal(Request $request)
    {
        $validated = $this->validateGoal($request);
        $user = $request->user();

        $user->usageGoal()->updateOrCreate(
            ['user_id' => $user->id, 'goalable_id' => $user->id, 'goalable_type' => get_class($user)],
            [
                'name' => $validated['name'],
                'target_kwh' => $validated['target_kwh'],
                'period' => $validated['period'],
            ]
        );

        return new UsageGoalResource($user->usageGoal);
    }

    // --- Group Goal Methods ---

    /**
     * Display the specified group's usage goal.
     */
    public function showGroupGoal(Group $group)
    {
        $this->authorize('view', $group); // Ensure user can view the group

        return new UsageGoalResource($group->usageGoals()->where('user_id', auth()->id())->first());
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
            ['user_id' => $user->id, 'goalable_id' => $group->id, 'goalable_type' => get_class($group)],
            [
                'name' => $validated['name'],
                'target_kwh' => $validated['target_kwh'],
                'period' => $validated['period'],
            ]
        );

        return new UsageGoalResource($group->usageGoals()->where('user_id', auth()->id())->first());
    }

    /**
     * Display a listing of all usage goals for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $goals = $user->allUsageGoals()->get();

        return UsageGoalResource::collection($goals);
    }
}
