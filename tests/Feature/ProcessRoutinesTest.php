<?php

use App\Models\Device;
use App\Models\Group;
use App\Models\Routine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use function Pest\Laravel\artisan;

// Manually manage database migrations and cleanup to avoid transaction isolation issues
// with Artisan commands that modify the database
beforeEach(function () {
    Artisan::call('migrate:fresh', ['--env' => 'testing', '--quiet' => true]);
    $this->user = User::factory()->create();
});

afterEach(function () {
    Artisan::call('migrate:rollback', ['--env' => 'testing', '--quiet' => true]);
});


test('the command executes a due routine on a device', function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 1, 8, 0, 0));

    $device = Device::factory()->create(['is_on' => false]);
    Routine::factory()->create([
        'user_id' => $this->user->id,
        'targetable_id' => $device->id,
        'targetable_type' => Device::class,
        'action' => 'turn_on',
        'cron_expression' => '0 8 * * *', // At 8:00
        'is_active' => true,
    ]);

    artisan('app:process-routines');

    $device->refresh();
    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'is_on' => true,
    ]);
});

test('the command executes a due routine on a group', function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 1, 22, 0, 0));

    $group = Group::factory()->create(['user_id' => $this->user->id]);
    $devices = Device::factory()->count(3)->create([
        'group_id' => $group->id,
        'user_id' => $group->user_id,
        'is_on' => true,
    ]);

    Routine::factory()->create([
        'user_id' => $this->user->id,
        'targetable_id' => $group->id,
        'targetable_type' => Group::class,
        'action' => 'turn_off',
        'cron_expression' => '0 22 * * *', // At 22:00
        'is_active' => true,
    ]);

    artisan('app:process-routines');

    foreach ($devices as $device) {
        $device->refresh();
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'is_on' => false,
        ]);
    }
});

test('the command does not execute a routine that is not due', function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 1, 10, 0, 0)); // It is 10:00

    $device = Device::factory()->create(['is_on' => false]);
    Routine::factory()->create([
        'user_id' => $this->user->id,
        'targetable_id' => $device->id,
        'targetable_type' => Device::class,
        'action' => 'turn_on',
        'cron_expression' => '0 8 * * *', // Due at 8:00
        'is_active' => true,
    ]);

    artisan('app:process-routines');

    $device->refresh();
    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'is_on' => false, // Stays false
    ]);
});

test('the command does not execute an inactive routine', function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 1, 8, 0, 0));

    $device = Device::factory()->create(['is_on' => false]);
    Routine::factory()->create([
        'user_id' => $this->user->id,
        'targetable_id' => $device->id,
        'targetable_type' => Device::class,
        'action' => 'turn_on',
        'cron_expression' => '0 8 * * *', // Due at 8:00
        'is_active' => false, // But inactive
    ]);

    artisan('app:process-routines');

    $device->refresh();
    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'is_on' => false, // Stays false
    ]);
});