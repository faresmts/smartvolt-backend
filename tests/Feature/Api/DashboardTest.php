<?php

use App\Models\Device;
use App\Models\DeviceReading;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create devices and groups for the main user
    $this->userDevices = Device::factory()->count(3)->create(['user_id' => $this->user->id, 'is_on' => true]);
    $this->userGroup = Group::factory()->create(['user_id' => $this->user->id]);
    $this->userDevices->first()->update(['group_id' => $this->userGroup->id]);

    // Create devices and groups for the other user
    $this->otherUserGroup = Group::factory()->create(['user_id' => $this->otherUser->id]);

    // Create readings for the main user's devices
    DeviceReading::factory()->count(10)->create([
        'device_id' => $this->userDevices->first()->id,
        'consumption' => 100, // 10 * 100 = 1000
        'voltage' => 120,
        'created_at' => now()->subHours(rand(1, 20)),
    ]);
    DeviceReading::factory()->count(5)->create([
        'device_id' => $this->userDevices->last()->id,
        'consumption' => 200, // 5 * 200 = 1000
        'voltage' => 125,
        'created_at' => now()->subDays(rand(1, 5)),
    ]);
});

test('unauthenticated users cannot access dashboard endpoints', function () {
    $this->getJson('/api/dashboard/summary')->assertUnauthorized();
    $this->getJson('/api/dashboard/consumption-history?period=24h')->assertUnauthorized();
    $this->getJson('/api/dashboard/voltage-history?period=24h')->assertUnauthorized();
});

test('summary endpoint returns correct stats', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/dashboard/summary')
        ->assertOk()
        ->assertJson([
            'total_consumption_kwh_last_30_days' => 2.0, // (1000 + 1000) / 1000
            'active_devices_count' => 3,
            'total_devices_count' => 3,
        ]);
});

test('consumption history endpoint returns data for 24h', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/dashboard/consumption-history?period=24h')
        ->assertOk()
        ->assertJsonStructure(['labels', 'values']);
});

test('voltage history endpoint returns data for 7d', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/dashboard/voltage-history?period=7d')
        ->assertOk()
        ->assertJsonStructure(['labels', 'values']);
});

test('history endpoints can be filtered by group', function () {
    Sanctum::actingAs($this->user);

    // This group only has the first device, so total consumption should be 1000 Wh or 1 kWh
    $response = $this->getJson('/api/dashboard/consumption-history?period=30d&group_id='.$this->userGroup->id)
        ->assertOk()
        ->assertJsonStructure(['labels', 'values']);

    $totalConsumption = array_sum($response->json('values'));
    expect($totalConsumption)->toEqual(1000.0);
});

test('user cannot get history for a group they do not own', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/dashboard/consumption-history?period=24h&group_id='.$this->otherUserGroup->id)
        ->assertStatus(422); // Validation error because the group_id does not exist for this user
});