<?php

namespace App\Console\Commands;

use App\Models\Routine;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessRoutines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-routines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all active routines and execute them if they are due.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing active routines...');

        $routines = Routine::where('is_active', true)->get();

        foreach ($routines as $routine) {
            $cron = new CronExpression($routine->cron_expression);
            if ($cron->isDue()) {
                $this->executeRoutine($routine);
            }
        }

        $this->info('All active routines have been processed.');
    }

    /**
     * Execute the given routine.
     *
     * @param \App\Models\Routine $routine
     * @return void
     */
    protected function executeRoutine(Routine $routine): void
    {
        Log::info("Executing routine: {$routine->name}");

        $target = $routine->targetable;
        $action = $routine->action; // 'turn_on' or 'turn_off'
        $is_on = $action === 'turn_on';

        Log::debug("Before update: Target ID: " . $target->id . ", Type: " . get_class($target) . ", is_on: " . ($target->is_on ? 'true' : 'false') . ". Intended is_on: " . ($is_on ? 'true' : 'false'));

        if ($target instanceof \App\Models\Device) {
            $target->update(['is_on' => $is_on]);
            Log::debug("After update (Device): Target ID: " . $target->id . ", is_on: " . ($target->fresh()->is_on ? 'true' : 'false'));
            $this->info("Device [{$target->name}] action [{$action}] executed.");
        } elseif ($target instanceof \App\Models\Group) {
            foreach ($target->devices as $device) {
                $device->update(['is_on' => $is_on]);
                Log::debug("After update (Group Device): Device ID: " . $device->id . ", is_on: " . ($device->fresh()->is_on ? 'true' : 'false'));
            }
            $this->info("Group [{$target->name}] action [{$action}] executed on all devices.");
        }
    }
}
