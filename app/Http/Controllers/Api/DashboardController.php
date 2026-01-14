<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    /**
     * Get a summary of the user's dashboard.
     */
    public function summary(Request $request)
    {
        $totalConsumption = $request->user()->devices()->sum('consumption');

        $topGroups = $request->user()->groups()
            ->withSum('devices as total_consumption', 'consumption')
            ->withCount(['devices', 'devices as devices_on_count' => function ($query) {
                $query->where('is_on', true);
            }])
            ->orderByDesc('total_consumption')
            ->limit(3)
            ->get();

        return response()->json([
            'total_consumption' => $totalConsumption,
            'top_groups' => $topGroups,
        ]);
    }

    /**
     * Get the consumption history for the user's devices.
     */
    public function consumptionHistory(Request $request)
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['24h', '7d', '30d'])],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('user_id', Auth::id())],
        ]);

        return $this->getHistoryData($validated['period'], $validated['group_id'] ?? null, 'consumption');
    }

    /**
     * Get the voltage history for the user's devices.
     */
    public function voltageHistory(Request $request)
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['24h', '7d', '30d'])],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('user_id', Auth::id())],
        ]);

        return $this->getHistoryData($validated['period'], $validated['group_id'] ?? null, 'voltage');
    }

    /**
     * Generic helper to fetch and format history data.
     */
    private function getHistoryData(string $period, ?int $groupId, string $metric)
    {
        $user = Auth::user();
        [$startDate, $dateFormat, $dbGrouping] = $this->getPeriodParameters($period);

        $query = DeviceReading::query()
            ->where('created_at', '>=', $startDate);

        // Scope to user's devices or a specific group
        if ($groupId) {
            $deviceIds = $user->groups()->findOrFail($groupId)->devices()->pluck('id');
        } else {
            $deviceIds = $user->devices()->pluck('id');
        }
        $query->whereIn('device_id', $deviceIds);

        $aggregator = $metric === 'consumption' ? 'SUM(consumption)' : 'AVG(voltage)';

        $results = $query
            ->select(
                DB::raw("$dbGrouping as label"),
                DB::raw("$aggregator as value")
            )
            ->groupBy('label')
            ->orderBy('label', 'asc')
            ->get();

        // Format for charting library
        return response()->json([
            'labels' => $results->pluck('label'),
            'values' => $results->pluck('value'),
        ]);
    }

    /**
     * Get the start date and date formatting based on the period.
     */
    private function getPeriodParameters(string $period): array
    {
        $dbDriver = DB::connection()->getDriverName();

        switch ($period) {
            case '24h':
                $startDate = now()->subHours(24);
                $dateFormat = 'H:00';
                $dbGrouping = $dbDriver === 'sqlite'
                    ? "strftime('%Y-%m-%d %H:00:00', created_at)"
                    : "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
                break;
            case '30d':
                $startDate = now()->subDays(30);
                $dateFormat = 'Y-m-d';
                $dbGrouping = $dbDriver === 'sqlite'
                    ? "strftime('%Y-%m-%d', created_at)"
                    : 'DATE(created_at)';
                break;
            case '7d':
            default:
                $startDate = now()->subDays(7);
                $dateFormat = 'Y-m-d';
                $dbGrouping = $dbDriver === 'sqlite'
                    ? "strftime('%Y-%m-%d', created_at)"
                    : 'DATE(created_at)';
                break;
        }

        return [$startDate, $dateFormat, $dbGrouping];
    }
}
