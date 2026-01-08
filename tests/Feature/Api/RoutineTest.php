<?php

use App\Models\Device;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->userDevice = Device::factory()->create(['user_id' => $this->user->id]);
    $this->otherUserDevice = Device::factory()->create(['user_id' => $this->otherUser->id]);

    $this->routine = Routine::factory()->create([
        'user_id' => $this->user->id,
        'targetable_id' => $this->userDevice->id,
        'targetable_type' => Device::class,
    ]);
});

test('unauthenticated users cannot access routine endpoints', function () {
    $this->getJson('/api/routines')->assertUnauthorized();
    $this->postJson('/api/routines')->assertUnauthorized();
    $this->getJson('/api/routines/' . $this->routine->id)->assertUnauthorized();
    $this->putJson('/api/routines/' . $this->routine->id)->assertUnauthorized();
    $this->deleteJson('/api/routines/' . $this->routine->id)->assertUnauthorized();
});

test('an authenticated user can create a routine for their device', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'name' => 'Morning On',
        'targetable_id' => $this->userDevice->id,
        'targetable_type' => 'App\Models\Device',
        'action' => 'turn_on',
        'cron_expression' => '0 8 * * *',
    ];

    $this->postJson('/api/routines', $data)
        ->assertStatus(201)
        ->assertJsonFragment(['name' => 'Morning On']);
});

test('an authenticated user cannot create a routine for another user device', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'name' => 'Malicious Routine',
        'targetable_id' => $this->otherUserDevice->id,
        'targetable_type' => 'App\Models\Device',
        'action' => 'turn_on',
        'cron_expression' => '0 8 * * *',
    ];

    $this->postJson('/api/routines', $data)->assertStatus(403);
});

test('an authenticated user can list their routines', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/routines')
        ->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $this->routine->id]);
});

test('an authenticated user can update a routine they own', function () {
    Sanctum::actingAs($this->user);

    $response = $this->putJson('/api/routines/' . $this->routine->id, [
        'name' => 'Evening Off',
        'cron_expression' => '0 22 * * *',
    ]);

    $response->assertStatus(200)
        ->assertJson(['name' => 'Evening Off']);
});

test('an authenticated user cannot update a routine they do not own', function () {
    $otherRoutine = Routine::factory()->create(['user_id' => $this->otherUser->id, 'targetable_id' => $this->otherUserDevice->id, 'targetable_type' => Device::class]);
    Sanctum::actingAs($this->user);

    $this->putJson('/api/routines/' . $otherRoutine->id, ['name' => 'New Name'])
        ->assertStatus(403);
});

test('an authenticated user can delete a routine they own', function () {
    Sanctum::actingAs($this->user);

    $this->deleteJson('/api/routines/' . $this->routine->id)
        ->assertStatus(204);

    $this->assertDatabaseMissing('routines', ['id' => $this->routine->id]);
});
