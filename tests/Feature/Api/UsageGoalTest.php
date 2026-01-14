<?php

use App\Models\Group;
use App\Models\UsageGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create a general user goal for the main user
    UsageGoal::factory()->create([
        'user_id' => $this->user->id,
        'goalable_id' => $this->user->id,
        'goalable_type' => User::class,
        'name' => 'User Monthly Goal',
        'target_kwh' => 500,
        'period' => 'monthly',
    ]);

    // Create a group for the main user
    $this->userGroup = Group::factory()->create(['user_id' => $this->user->id]);
    // Create a group goal for the main user's group
    UsageGoal::factory()->create([
        'user_id' => $this->user->id,
        'goalable_id' => $this->userGroup->id,
        'goalable_type' => Group::class,
        'name' => 'Living Room Weekly Goal',
        'target_kwh' => 150,
        'period' => 'weekly',
    ]);

    // Create a group for another user
    $this->otherUserGroup = Group::factory()->create(['user_id' => $this->otherUser->id]);
});

test('unauthenticated users cannot access any usage goal endpoints', function () {
    $this->getJson('/api/user/usage-goal')->assertUnauthorized();
    $this->postJson('/api/user/usage-goal')->assertUnauthorized();
    $this->getJson('/api/groups/'.$this->userGroup->id.'/usage-goal')->assertUnauthorized();
    $this->postJson('/api/groups/'.$this->userGroup->id.'/usage-goal')->assertUnauthorized();
});

test('an authenticated user can view their general usage goal', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/user/usage-goal');

    $response->assertOk()
        ->assertJsonFragment([
            'goalable_id' => $this->user->id,
            'goalable_type' => User::class,
            'name' => 'User Monthly Goal',
            'target_kwh' => 500,
            'period' => 'monthly',
        ]);
});

test('an authenticated user can create their general usage goal', function () {
    // Remove the beforeEach user goal to test creation
    UsageGoal::where('user_id', $this->user->id)->where('goalable_type', User::class)->delete();

    Sanctum::actingAs($this->user);

    $data = [
        'name' => 'New User Daily Goal',
        'target_kwh' => 600,
        'period' => 'daily',
    ];

    $response = $this->postJson('/api/user/usage-goal', $data);

    $response->assertStatus(200) // updateOrCreate returns 200
        ->assertJsonFragment($data);

    $this->assertDatabaseHas('usage_goals', [
        'user_id' => $this->user->id,
        'goalable_id' => $this->user->id,
        'goalable_type' => User::class,
        'name' => 'New User Daily Goal',
        'target_kwh' => 600,
        'period' => 'daily',
    ]);
});

test('an authenticated user can update their general usage goal', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'name' => 'Updated User Weekly Goal',
        'target_kwh' => 700,
        'period' => 'weekly',
    ];

    $response = $this->postJson('/api/user/usage-goal', $data);

    $response->assertOk()
        ->assertJsonFragment($data);

    $this->assertDatabaseHas('usage_goals', [
        'user_id' => $this->user->id,
        'goalable_id' => $this->user->id,
        'goalable_type' => User::class,
        'name' => 'Updated User Weekly Goal',
        'target_kwh' => 700,
        'period' => 'weekly',
    ]);
});

test('an authenticated user can view a group usage goal they own', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/groups/'.$this->userGroup->id.'/usage-goal');

    $response->assertOk()
        ->assertJsonFragment([
            'goalable_id' => $this->userGroup->id,
            'goalable_type' => Group::class,
            'name' => 'Living Room Weekly Goal',
            'target_kwh' => 150,
            'period' => 'weekly',
        ]);
});

test('an authenticated user cannot view a group usage goal they do not own', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/groups/'.$this->otherUserGroup->id.'/usage-goal');

    $response->assertStatus(403);
});

test('an authenticated user can create a group usage goal', function () {
    // Remove the beforeEach group goal to test creation
    UsageGoal::where('user_id', $this->user->id)->where('goalable_type', Group::class)->delete();

    Sanctum::actingAs($this->user);

    $data = [
        'name' => 'New Group Daily Goal',
        'target_kwh' => 200,
        'period' => 'daily',
    ];

    $response = $this->postJson('/api/groups/'.$this->userGroup->id.'/usage-goal', $data);

    $response->assertStatus(200)
        ->assertJsonFragment($data);

    $this->assertDatabaseHas('usage_goals', [
        'user_id' => $this->user->id,
        'goalable_id' => $this->userGroup->id,
        'goalable_type' => Group::class,
        'name' => 'New Group Daily Goal',
        'target_kwh' => 200,
        'period' => 'daily',
    ]);
});

test('an authenticated user can update a group usage goal', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'name' => 'Updated Group Monthly Goal',
        'target_kwh' => 250,
        'period' => 'monthly',
    ];

    $response = $this->postJson('/api/groups/'.$this->userGroup->id.'/usage-goal', $data);

    $response->assertOk()
        ->assertJsonFragment($data);

    $this->assertDatabaseHas('usage_goals', [
        'user_id' => $this->user->id,
        'goalable_id' => $this->userGroup->id,
        'goalable_type' => Group::class,
        'name' => 'Updated Group Monthly Goal',
        'target_kwh' => 250,
        'period' => 'monthly',
    ]);
});

test('an authenticated user cannot create/update a group usage goal for a group they do not own', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'name' => 'Other User Group Goal',
        'target_kwh' => 100,
        'period' => 'daily',
    ];

    $response = $this->postJson('/api/groups/'.$this->otherUserGroup->id.'/usage-goal', $data);

    $response->assertStatus(403);
});

// Test validation
test('usage goal validation fails with invalid data', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'name' => '', // Invalid: empty
        'target_kwh' => -10, // Invalid: negative
        'period' => 'invalid-period', // Invalid
    ];

    $response = $this->postJson('/api/user/usage-goal', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'target_kwh', 'period']);
});

test('usage goal validation fails with missing data', function () {
    Sanctum::actingAs($this->user);

    $data = []; // Missing all required fields

    $response = $this->postJson('/api/user/usage-goal', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'target_kwh', 'period']);
});

test('an authenticated user can list all their usage goals with current consumption and target names', function () {
    Sanctum::actingAs($this->user);

    // Ensure devices exist for user and group for consumption calculation
    $userDevice1 = \App\Models\Device::factory()->create([
        'user_id' => $this->user->id,
        'group_id' => null,
        'consumption' => 50,
    ]);
    $userDevice2 = \App\Models\Device::factory()->create([
        'user_id' => $this->user->id,
        'group_id' => $this->userGroup->id, // This device belongs to userGroup
        'consumption' => 100,
    ]);
    $userDevice3 = \App\Models\Device::factory()->create([
        'user_id' => $this->user->id,
        'group_id' => $this->userGroup->id, // This device belongs to userGroup
        'consumption' => 200,
    ]);

    // Create an additional goal for the user to ensure all goals are listed
    $userGoal2 = UsageGoal::factory()->create([
        'user_id' => $this->user->id,
        'goalable_id' => $this->user->id,
        'goalable_type' => User::class,
        'name' => 'User Daily Goal',
        'target_kwh' => 100,
        'period' => 'daily',
    ]);

    // Create a goal for another user (should not be in response)
    $otherUserGoal = UsageGoal::factory()->create([
        'user_id' => $this->otherUser->id,
        'goalable_id' => $this->otherUser->id,
        'goalable_type' => User::class,
        'name' => 'Other User Goal',
        'target_kwh' => 999,
        'period' => 'monthly',
    ]);

    $response = $this->getJson('/api/usage-goals');

    $response->assertOk()
        ->assertJsonFragment([
            'name' => 'User Monthly Goal',
            'goalable_type' => User::class,
            'goalable_id' => $this->user->id,
            'target_kwh' => 500,
            'period' => 'monthly',
            'current_consumption' => 350, // 50 + 100 + 200 (all user devices)
            'goal_target_name' => 'Meta Geral',
        ])
        ->assertJsonFragment([
            'name' => 'Living Room Weekly Goal',
            'goalable_type' => Group::class,
            'goalable_id' => $this->userGroup->id,
            'target_kwh' => 150,
            'period' => 'weekly',
            'current_consumption' => 300, // 100 + 200 (devices in userGroup)
            'goal_target_name' => $this->userGroup->name,
        ])
        ->assertJsonFragment([
            'name' => 'User Daily Goal',
            'goalable_type' => User::class,
            'goalable_id' => $this->user->id,
            'target_kwh' => 100,
            'period' => 'daily',
            'current_consumption' => 350, // 50 + 100 + 200 (all user devices)
            'goal_target_name' => 'Meta Geral',
        ])
        ->assertJsonMissing([
            'name' => 'Other User Goal',
            'target_kwh' => 999,
        ]);
});
