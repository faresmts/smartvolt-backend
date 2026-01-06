<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->userGroups = Group::factory()->count(3)->create(['user_id' => $this->user->id]);
    $this->otherUserGroup = Group::factory()->create(['user_id' => $this->otherUser->id]);
});

test('unauthenticated users cannot access group endpoints', function () {
    $this->getJson('/api/groups')->assertUnauthorized();
    $this->postJson('/api/groups')->assertUnauthorized();
    $this->getJson('/api/groups/'.$this->userGroups->first()->id)->assertUnauthorized();
    $this->putJson('/api/groups/'.$this->userGroups->first()->id)->assertUnauthorized();
    $this->deleteJson('/api/groups/'.$this->userGroups->first()->id)->assertUnauthorized();
});

test('an authenticated user can get a list of their own groups', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/groups');

    $response->assertStatus(200)
        ->assertJsonCount(3)
        ->assertJsonFragment(['name' => $this->userGroups->first()->name]);
});

test('an authenticated user cannot get a list of another user groups', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/groups');

    $response->assertStatus(200)
        ->assertJsonMissing(['name' => $this->otherUserGroup->name]);
});

test('an authenticated user can create a group', function () {
    Sanctum::actingAs($this->user);

    $groupName = 'New Test Group';
    $response = $this->postJson('/api/groups', ['name' => $groupName]);

    $response->assertStatus(201)
        ->assertJson(['name' => $groupName]);

    $this->assertDatabaseHas('groups', [
        'user_id' => $this->user->id,
        'name' => $groupName,
    ]);
});

test('an authenticated user can get a specific group they own', function () {
    Sanctum::actingAs($this->user);

    $group = $this->userGroups->first();
    $response = $this->getJson('/api/groups/'.$group->id);

    $response->assertStatus(200)
        ->assertJson(['name' => $group->name]);
});

test('an authenticated user cannot get a group they do not own', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/groups/'.$this->otherUserGroup->id);

    $response->assertStatus(403);
});

test('an authenticated user can update a group they own', function () {
    Sanctum::actingAs($this->user);

    $group = $this->userGroups->first();
    $newName = 'Updated Group Name';
    $response = $this->putJson('/api/groups/'.$group->id, ['name' => $newName]);

    $response->assertStatus(200)
        ->assertJson(['name' => $newName]);

    $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => $newName]);
});

test('an authenticated user cannot update a group they do not own', function () {
    Sanctum::actingAs($this->user);

    $newName = 'Updated Group Name';
    $response = $this->putJson('/api/groups/'.$this->otherUserGroup->id, ['name' => $newName]);

    $response->assertStatus(403);
});

test('an authenticated user can delete a group they own', function () {
    Sanctum::actingAs($this->user);

    $group = $this->userGroups->first();
    $response = $this->deleteJson('/api/groups/'.$group->id);

    $response->assertStatus(204);

    $this->assertDatabaseMissing('groups', ['id' => $group->id]);
});

test('an authenticated user cannot delete a group they do not own', function () {
    Sanctum::actingAs($this->user);

    $response = $this->deleteJson('/api/groups/'.$this->otherUserGroup->id);

    $response->assertStatus(403);
});

test('deleting a group does not delete its devices', function () {
    Sanctum::actingAs($this->user);

    $group = $this->userGroups->first();
    $device = \App\Models\Device::factory()->create([
        'user_id' => $this->user->id,
        'group_id' => $group->id,
    ]);

    $this->deleteJson('/api/groups/'.$group->id);

    $this->assertDatabaseHas('devices', ['id' => $device->id])
        ->assertDatabaseHas('devices', ['id' => $device->id, 'group_id' => null]);
});
