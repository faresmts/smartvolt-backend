<?php

use App\Models\Device;
use App\Models\Group;
use App\Models\UsageGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->userDevice = Device::factory()->create(['user_id' => $this->user->id]);
    $this->userGroup = Group::factory()->create(['user_id' => $this->user->id]);

    $this->otherUserDevice = Device::factory()->create(['user_id' => $this->otherUser->id]);

    $this->goal = UsageGoal::factory()->create([
        'user_id' => $this->user->id,
        'goalable_id' => $this->userDevice->id,
        'goalable_type' => Device::class,
    ]);
});

test('unauthenticated users cannot access usage goal endpoints', function () {
    $this->getJson('/api/usage-goals')->assertUnauthorized();
    $this->postJson('/api/usage-goals')->assertUnauthorized();
    $this->getJson('/api/usage-goals/'.$this->goal->id)->assertUnauthorized();
    $this->putJson('/api/usage-goals/'.$this->goal->id)->assertUnauthorized();
    $this->deleteJson('/api/usage-goals/'.$this->goal->id)->assertUnauthorized();
});

test('an authenticated user can create a usage goal for their device', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'goalable_id' => $this->userDevice->id,
        'goalable_type' => 'App\Models\Device',
        'target_kwh' => 100,
        'period' => 'monthly',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ];

    $this->postJson('/api/usage-goals', $data)
        ->assertStatus(201)
        ->assertJsonFragment(['target_kwh' => 100]);

    $this->assertDatabaseHas('usage_goals', [
        'user_id' => $this->user->id,
        'goalable_id' => $this->userDevice->id,
        'goalable_type' => Device::class,
    ]);
});

test('an authenticated user cannot create a usage goal for another user device', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'goalable_id' => $this->otherUserDevice->id,
        'goalable_type' => 'App\Models\Device',
        'target_kwh' => 100,
        'period' => 'monthly',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ];

    $this->postJson('/api/usage-goals', $data)->assertStatus(403);
});

test('an authenticated user can list their usage goals', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/usage-goals')
        ->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $this->goal->id]);
});

test('an authenticated user can update a usage goal they own', function () {
    Sanctum::actingAs($this->user);

    $response = $this->putJson('/api/usage-goals/'.$this->goal->id, ['target_kwh' => 150]);

    $response->assertStatus(200)
        ->assertJson(['target_kwh' => 150]);
});

test('an authenticated user cannot update a usage goal they do not own', function () {
    $otherGoal = UsageGoal::factory()->create(['user_id' => $this->otherUser->id, 'goalable_id' => $this->otherUserDevice->id, 'goalable_type' => Device::class]);

    Sanctum::actingAs($this->user);

    $this->putJson('/api/usage-goals/'.$otherGoal->id, ['target_kwh' => 150])
        ->assertStatus(403);
});

test('an authenticated user can delete a usage goal they own', function () {
    Sanctum::actingAs($this->user);

    $this->deleteJson('/api/usage-goals/'.$this->goal->id)
        ->assertStatus(204);

    $this->assertDatabaseMissing('usage_goals', ['id' => $this->goal->id]);
});
