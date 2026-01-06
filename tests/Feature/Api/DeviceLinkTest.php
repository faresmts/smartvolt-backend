<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->unlinkedDevice = Device::factory()->create(['user_id' => null]);
    $this->linkedDevice = Device::factory()->create(['user_id' => $this->otherUser->id]);
});

test('an authenticated user can link an unlinked device with a valid key', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/devices/link', ['key' => $this->unlinkedDevice->key]);

    $response->assertStatus(200)
        ->assertJsonFragment(['user_id' => $this->user->id]);

    $this->assertDatabaseHas('devices', [
        'id' => $this->unlinkedDevice->id,
        'user_id' => $this->user->id,
    ]);
});

test('a user cannot link a device with an invalid key', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/devices/link', ['key' => 'invalid-key']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('key');
});

test('a user cannot link a device that is already linked to another user', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/devices/link', ['key' => $this->linkedDevice->key]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Device is already linked.']);
});

test('an unauthenticated user cannot link a device', function () {
    $response = $this->postJson('/api/devices/link', ['key' => $this->unlinkedDevice->key]);

    $response->assertUnauthorized();
});
