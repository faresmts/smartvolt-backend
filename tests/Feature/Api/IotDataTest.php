<?php

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a device can successfully report its status and metrics', function () {
    $device = Device::factory()->create([
        'is_on' => false,
        'voltage' => 0.0,
        'consumption' => 0.0,
    ]);

    $data = [
        'key' => $device->key,
        'is_on' => true,
        'voltage' => 220.50,
        'consumption' => 123.4567,
    ];

    $response = $this->postJson('/api/iot/report', $data);

    $response->assertStatus(204); // No Content

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'is_on' => true,
        'voltage' => 220.50,
        'consumption' => 123.4567,
    ]);

    $this->assertDatabaseHas('device_readings', [
        'device_id' => $device->id,
        'is_on' => true,
        'voltage' => 220.50,
        'consumption' => 123.4567,
    ]);
});

test('data ingestion fails with missing required fields', function () {
    $device = Device::factory()->create();

    $data = [
        'key' => $device->key,
        // 'is_on' is missing
        'voltage' => 220.50,
        'consumption' => 123.4567,
    ];

    $response = $this->postJson('/api/iot/report', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('is_on');
});

test('data ingestion fails with an invalid device key', function () {
    $data = [
        'key' => 'invalid-key',
        'is_on' => true,
        'voltage' => 220.50,
        'consumption' => 123.4567,
    ];

    $response = $this->postJson('/api/iot/report', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('key');
});

test('data ingestion fails with invalid data types', function () {
    $device = Device::factory()->create();

    $data = [
        'key' => $device->key,
        'is_on' => 'not-a-boolean',
        'voltage' => 'not-a-number',
        'consumption' => 123.4567,
    ];

    $response = $this->postJson('/api/iot/report', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['is_on', 'voltage']);
});
