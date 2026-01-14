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

test('an authenticated user can get a specific group they own with devices and total consumption', function () {
    Sanctum::actingAs($this->user);

    $group = $this->userGroups->first();

    $device1 = \App\Models\Device::factory()->create([
        'user_id' => $this->user->id,
        'group_id' => $group->id,
        'is_on' => true,
        'consumption' => 100,
    ]);

    $device2 = \App\Models\Device::factory()->create([
        'user_id' => $this->user->id,
        'group_id' => $group->id,
        'is_on' => false,
        'consumption' => 150,
    ]);

    $response = $this->getJson('/api/groups/'.$group->id);

    $response->assertStatus(200)
        ->assertJson([
            'id' => $group->id,
            'name' => $group->name,
            'total_consumption' => 250,
            'devices' => [
                [
                    'id' => $device1->id,
                    'name' => $device1->name,
                    'consumption' => 100,
                    'is_on' => true,
                ],
                [
                    'id' => $device2->id,
                    'name' => $device2->name,
                    'consumption' => 150,
                    'is_on' => false,
                ],
            ],
        ]);
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

test('an authenticated user can get a list of their own groups with device counts and consumption', function () {
    Sanctum::actingAs($this->user);

    $group = $this->userGroups->first();

    \App\Models\Device::factory()->create([
        'user_id' => $this->user->id,
        'group_id' => $group->id,
        'is_on' => true,
        'consumption' => 100,
    ]);

    \App\Models\Device::factory()->create([
        'user_id' => $this->user->id,
        'group_id' => $group->id,
        'is_on' => true,
        'consumption' => 150,
    ]);

    \App\Models\Device::factory()->create([
        'user_id' => $this->user->id,
        'group_id' => $group->id,
        'is_on' => false,
        'consumption' => 200,
    ]);

    $response = $this->getJson('/api/groups');

    $response->assertStatus(200)
        ->assertJsonFragment([
            'id' => $group->id,
            'devices_count' => 3,
            'devices_on_count' => 2,
            'total_consumption' => 450,
        ]);
});

test('an authenticated user can create a group and attach devices', function () {
    Sanctum::actingAs($this->user);
    $devices = \App\Models\Device::factory()->count(2)->create(['user_id' => $this->user->id]);

    $response = $this->postJson('/api/groups', [
        'name' => 'New Group with Devices',
        'device_ids' => $devices->pluck('id')->toArray(),
    ]);

    $response->assertStatus(201);
    $groupId = $response->json('id');
    $this->assertDatabaseHas('groups', ['id' => $groupId, 'name' => 'New Group with Devices']);
    foreach ($devices as $device) {
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'group_id' => $groupId]);
    }
});

test('an authenticated user cannot create a group with devices from another user', function () {
    Sanctum::actingAs($this->user);
    $otherUserDevice = \App\Models\Device::factory()->create(['user_id' => $this->otherUser->id]);

    $response = $this->postJson('/api/groups', [
        'name' => 'Fraudulent Group',
        'device_ids' => [$otherUserDevice->id],
    ]);

    $response->assertStatus(422);
});

test('an authenticated user can update a group and change attached devices', function () {
    Sanctum::actingAs($this->user);
    $group = $this->userGroups->first();
    $oldDevice = \App\Models\Device::factory()->create(['user_id' => $this->user->id, 'group_id' => $group->id]);
    $newDevices = \App\Models\Device::factory()->count(2)->create(['user_id' => $this->user->id]);

    $response = $this->putJson('/api/groups/'.$group->id, [
        'device_ids' => $newDevices->pluck('id')->toArray(),
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('devices', ['id' => $oldDevice->id, 'group_id' => null]);
    foreach ($newDevices as $device) {
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'group_id' => $group->id]);
    }
});

test('an authenticated user cannot update a group with devices from another user', function () {
    Sanctum::actingAs($this->user);
    $group = $this->userGroups->first();
    $otherUserDevice = \App\Models\Device::factory()->create(['user_id' => $this->otherUser->id]);

    $response = $this->putJson('/api/groups/'.$group->id, [
        'device_ids' => [$otherUserDevice->id],
    ]);

    $response->assertStatus(422);
});

test('an authenticated user can create a group and move a device from another group', function () {
    Sanctum::actingAs($this->user);
    $groupA = Group::factory()->create(['user_id' => $this->user->id]);
    $device = \App\Models\Device::factory()->create(['user_id' => $this->user->id, 'group_id' => $groupA->id]);

    $response = $this->postJson('/api/groups', [
        'name' => 'Group B',
        'device_ids' => [$device->id],
    ]);

    $response->assertStatus(201);
    $groupBId = $response->json('id');
    $this->assertDatabaseHas('devices', ['id' => $device->id, 'group_id' => $groupBId]);
    $this->assertDatabaseMissing('devices', ['id' => $device->id, 'group_id' => $groupA->id]);
});

test('an authenticated user can unlink a device from a group', function () {
    Sanctum::actingAs($this->user);
    $group = $this->userGroups->first();
    $device = \App\Models\Device::factory()->create(['user_id' => $this->user->id, 'group_id' => $group->id]);

    $response = $this->postJson('/api/groups/'.$group->id.'/unlink-device/'.$device->id);

    $response->assertStatus(204);
    $this->assertDatabaseHas('devices', ['id' => $device->id, 'group_id' => null]);
});

test('an authenticated user cannot unlink a device from another user', function () {
    Sanctum::actingAs($this->user);
    $otherUserGroup = Group::factory()->create(['user_id' => $this->otherUser->id]);
    $otherUserDevice = \App\Models\Device::factory()->create(['user_id' => $this->otherUser->id, 'group_id' => $otherUserGroup->id]);

    $response = $this->postJson('/api/groups/'.$otherUserGroup->id.'/unlink-device/'.$otherUserDevice->id);

    $response->assertStatus(403);
});

test('an authenticated user cannot unlink a device from a group if the device does not belong to the group', function () {
    Sanctum::actingAs($this->user);
    $group = $this->userGroups->first();
    $anotherGroup = Group::factory()->create(['user_id' => $this->user->id]);
    $device = \App\Models\Device::factory()->create(['user_id' => $this->user->id, 'group_id' => $anotherGroup->id]);

    $response = $this->postJson('/api/groups/'.$group->id.'/unlink-device/'.$device->id);

    $response->assertStatus(400);
});
