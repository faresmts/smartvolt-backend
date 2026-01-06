<?php

use App\Models\Device;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->userDevices = Device::factory()->count(3)->create(['user_id' => $this->user->id]);
    $this->otherUserDevice = Device::factory()->create(['user_id' => $this->otherUser->id]);
    $this->userGroup = Group::factory()->create(['user_id' => $this->user->id]);
    $this->otherUserGroup = Group::factory()->create(['user_id' => $this->otherUser->id]);
});

test('unauthenticated users cannot access device endpoints', function () {
    $this->getJson('/api/devices')->assertUnauthorized();
    $this->getJson('/api/devices/'.$this->userDevices->first()->id)->assertUnauthorized();
    $this->putJson('/api/devices/'.$this->userDevices->first()->id)->assertUnauthorized();
    $this->deleteJson('/api/devices/'.$this->userDevices->first()->id)->assertUnauthorized();
});

test('an authenticated user can get a list of their own devices', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/devices');

    $response->assertStatus(200)
        ->assertJsonCount(3)
        ->assertJsonFragment(['name' => $this->userDevices->first()->name]);
});

test('an authenticated user cannot get a list of another user devices', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/devices');

    $response->assertStatus(200)
        ->assertJsonMissing(['name' => $this->otherUserDevice->name]);
});

test('an authenticated user can get a specific device they own', function () {
    Sanctum::actingAs($this->user);

    $device = $this->userDevices->first();
    $response = $this->getJson('/api/devices/'.$device->id);

    $response->assertStatus(200)
        ->assertJson(['name' => $device->name]);
});

test('an authenticated user cannot get a device they do not own', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/devices/'.$this->otherUserDevice->id);

    $response->assertStatus(403);
});

test('an authenticated user can update a device they own', function () {
    Sanctum::actingAs($this->user);

    $device = $this->userDevices->first();
    $newName = 'Updated Device Name';
    $response = $this->putJson('/api/devices/'.$device->id, [
        'name' => $newName,
        'group_id' => $this->userGroup->id,
    ]);

    $response->assertStatus(200)
        ->assertJson(['name' => $newName, 'group_id' => $this->userGroup->id]);

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'name' => $newName,
        'group_id' => $this->userGroup->id,
    ]);
});

test('an authenticated user cannot update a device they do not own', function () {
    Sanctum::actingAs($this->user);

    $newName = 'Updated Device Name';
    $response = $this->putJson('/api/devices/'.$this->otherUserDevice->id, ['name' => $newName]);

    $response->assertStatus(403);
});

test('an authenticated user cannot assign a device to a group that belongs to another user', function () {
    Sanctum::actingAs($this->user);

    $device = $this->userDevices->first();
    $response = $this->putJson('/api/devices/'.$device->id, [
        'group_id' => $this->otherUserGroup->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('group_id');
});

test('an authenticated user can delete a device they own', function () {
    Sanctum::actingAs($this->user);

    $device = $this->userDevices->first();
    $response = $this->deleteJson('/api/devices/'.$device->id);

    $response->assertStatus(204);

    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
});

test('an authenticated user cannot delete a device they do not own', function () {
    Sanctum::actingAs($this->user);

    $response = $this->deleteJson('/api/devices/'.$this->otherUserDevice->id);

    $response->assertStatus(403);
});
