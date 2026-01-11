<?php

use App\Models\Device;
use App\Models\Group;
use App\Models\Routine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->command = new \App\Console\Commands\ProcessRoutines();
    $this->command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput()));
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

    $this->command->handle();

    $device->refresh();
    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'is_on' => 1,
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

    $this->command->handle();

    foreach ($devices as $device) {
        $device->refresh();
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'is_on' => 0,
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

    $this->command->handle();

    $device->refresh();
    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'is_on' => 0, // Stays false
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

    $this->command->handle();

    $device->refresh();
    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'is_on' => 0, // Stays false
    ]);
});
