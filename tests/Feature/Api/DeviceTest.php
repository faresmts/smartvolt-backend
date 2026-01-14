<?php

use App\Models\Device;
use App\Models\Group;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->userDevices = Device::factory()->count(3)->create(['user_id' => $this->user->id, 'ip_address' => '127.0.0.1']);
    $this->otherUserDevice = Device::factory()->create(['user_id' => $this->otherUser->id, 'ip_address' => '127.0.0.1']);
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
    $newIpAddress = '192.168.1.100';
    $newType = 'plug';
    $response = $this->putJson('/api/devices/'.$device->id, [
        'name' => $newName,
        'group_id' => $this->userGroup->id,
        'ip_address' => $newIpAddress,
        'type' => $newType,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'name' => $newName,
            'group_id' => $this->userGroup->id,
            'ip_address' => $newIpAddress,
            'type' => $newType,
        ]);

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'name' => $newName,
        'group_id' => $this->userGroup->id,
        'ip_address' => $newIpAddress,
        'type' => $newType,
    ]);
});

test('an authenticated user can link a device with ip_address and type', function () {
    Sanctum::actingAs($this->user);
    $unlinkedDevice = Device::factory()->create(['user_id' => null, 'key' => 'UNLINKED_KEY']);

    $response = $this->postJson('/api/devices/link', [
        'key' => 'UNLINKED_KEY',
        'name' => 'Linked Device',
        'ip_address' => '192.168.1.10',
        'type' => 'plug',
    ]);

    $response->assertOk()
        ->assertJson([
            'user_id' => $this->user->id,
            'name' => 'Linked Device',
            'ip_address' => '192.168.1.10',
            'type' => 'plug',
        ]);

    $this->assertDatabaseHas('devices', [
        'id' => $unlinkedDevice->id,
        'user_id' => $this->user->id,
        'name' => 'Linked Device',
        'ip_address' => '192.168.1.10',
        'type' => 'plug',
    ]);
});

test('device link validation fails with invalid type', function () {
    Sanctum::actingAs($this->user);
    $unlinkedDevice = Device::factory()->create(['user_id' => null, 'key' => 'ANOTHER_UNLINKED_KEY']);

    $response = $this->postJson('/api/devices/link', [
        'key' => 'ANOTHER_UNLINKED_KEY',
        'name' => 'Invalid Type Device',
        'type' => 'invalid_type',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

test('device update validation fails with invalid type', function () {
    Sanctum::actingAs($this->user);
    $device = $this->userDevices->first();

    $response = $this->putJson('/api/devices/'.$device->id, [
        'type' => 'invalid_type',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('type');
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

    $this->assertDatabaseHas('devices', ['id' => $device->id]);
});

test('an authenticated user cannot delete a device they do not own', function () {
    Sanctum::actingAs($this->user);

    $response = $this->deleteJson('/api/devices/'.$this->otherUserDevice->id);

    $response->assertStatus(403);
});

test('an authenticated user can toggle a device\'s on/off status', function () {
    Sanctum::actingAs($this->user);

    // Mock Guzzle responses
    $mock = new MockHandler([
        // Initial status check: device is ON
        new Psr7\Response(200, [], json_encode(['relay_state' => true])),
        // Toggle OFF command response
        new Psr7\Response(200, [], json_encode(['relay_state' => false])),
        // Status check after toggle OFF: device is OFF
        new Psr7\Response(200, [], json_encode(['relay_state' => false])),
        // Toggle ON command response
        new Psr7\Response(200, [], json_encode(['relay_state' => true])),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);

    // Replace the Guzzle client in the container with the mock
    $this->app->instance(Client::class, $client);

    $device = Device::factory()->create([
        'user_id' => $this->user->id,
        'is_on' => true,
        'ip_address' => '192.168.1.100', // Needs an IP address for the controller logic
    ]);

    // Toggle off
    $response = $this->postJson('/api/devices/'.$device->id.'/toggle');
    $response->assertOk()
        ->assertJson([
            'message' => 'Device toggled successfully.',
            'is_on' => false,
        ]);
    $this->assertDatabaseHas('devices', ['id' => $device->id, 'is_on' => false]);

    // Toggle on
    $response = $this->postJson('/api/devices/'.$device->id.'/toggle');
    $response->assertOk()
        ->assertJson([
            'message' => 'Device toggled successfully.',
            'is_on' => true,
        ]);
    $this->assertDatabaseHas('devices', ['id' => $device->id, 'is_on' => true]);
});

test('an authenticated user cannot toggle a device they do not own', function () {
    Sanctum::actingAs($this->user); // Acting as the main user
    $otherUserDevice = Device::factory()->create(['user_id' => $this->otherUser->id, 'is_on' => true, 'ip_address' => '127.0.0.1']);

    $response = $this->postJson('/api/devices/'.$otherUserDevice->id.'/toggle');

    $response->assertStatus(403);
});