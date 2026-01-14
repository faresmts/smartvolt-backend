<?php

namespace App\Http\Resources;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsageGoalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentConsumption = 0;
        $goalTargetName = 'N/A';

        if ($this->goalable_type === User::class) {
            $currentConsumption = $this->user->devices()->sum('consumption');
            $goalTargetName = 'Meta Geral';
        } elseif ($this->goalable_type === Group::class) {
            $group = $this->goalable; // This loads the associated group
            if ($group) {
                $group->loadSum('devices as total_consumption', 'consumption');
                $currentConsumption = $group->total_consumption;
                $goalTargetName = $group->name;
            } else {
                $currentConsumption = 0;
                $goalTargetName = 'Grupo Não Encontrado'; // Fallback
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'goalable_id' => $this->goalable_id,
            'goalable_type' => $this->goalable_type,
            'name' => $this->name,
            'target_kwh' => $this->target_kwh,
            'period' => $this->period,
            'current_consumption' => $currentConsumption,
            'goal_target_name' => $goalTargetName,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
