use App\Models\Device;
use App\Models\EnergyReading;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('monitor:devices command stores energy readings and updates device status', function () {
    // Arrange
    $device = Device::factory()->create([
        'ip_address' => '192.168.1.100',
        'is_on' => false,
    ]);

    // Mock Guzzle responses for /status endpoint
    $mock = new MockHandler([
        new Psr7\Response(200, [], json_encode([
            'voltage_rms' => 220.5,
            'current_rms' => 0.5,
            'temperature' => 25.0,
            'power' => 110.25,
            'energy' => 1.5, // kWh
            'cost' => 0.75,
            'relay_state' => true, // Device is ON
        ])),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);

    // Replace the Guzzle client in the container with the mock
    $this->app->instance(Client::class, $client);

    // Act
    $this->artisan('monitor:devices')
        ->assertExitCode(0);

    // Assert
    $this->assertDatabaseHas('energy_readings', [
        'device_id' => $device->id,
        'kwh_consumption' => 1.5,
        'relay_status' => true,
        'voltage_rms' => 220.5,
        'current_rms' => 0.5,
        'power' => 110.25,
        'energy' => 1.5,
        'cost' => 0.75,
    ]);

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'is_on' => true, // Device status should be updated
    ]);

    expect(EnergyReading::count())->toBe(1);
});

test('monitor:devices command logs error for device without ip address', function () {
    // Arrange
    $device = Device::factory()->create([
        'ip_address' => null, // No IP address
    ]);

    // Expect a log warning
    Log::shouldReceive('warning')
        ->once()
        ->with("Device {$device->id} has no IP address configured. Skipping monitoring.");

    // Act
    $this->artisan('monitor:devices')
        ->assertExitCode(0);

    // Assert no readings were stored
    expect(EnergyReading::count())->toBe(0);
});

test('monitor:devices command logs error for failed http request', function () {
    // Arrange
    $device = Device::factory()->create([
        'ip_address' => '192.168.1.101',
    ]);

    // Mock a Guzzle RequestException
    $mock = new MockHandler([
        new RequestException('Server Error', new Psr7\Request('GET', '/status')),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);
    $this->app->instance(Client::class, $client);

    // Expect a log error
    Log::shouldReceive('error')
        ->once()
        ->with(\Mockery::on(function ($message) use ($device) {
            return Str::contains($message, "Failed to get status for device {$device->id} at {$device->ip_address}");
        }));

    // Act
    $this->artisan('monitor:devices')
        ->assertExitCode(0);

    // Assert no readings were stored
    expect(EnergyReading::count())->toBe(0);
});

test('monitor:devices command logs error for invalid json response', function () {
    // Arrange
    $device = Device::factory()->create([
        'ip_address' => '192.168.1.102',
    ]);

    // Mock an invalid JSON response
    $mock = new MockHandler([
        new Psr7\Response(200, [], 'This is not JSON'),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);
    $this->app->instance(Client::class, $client);

    // Expect a log error
    Log::shouldReceive('error')
        ->once()
        ->with("Failed to parse JSON response for device {$device->id} at {$device->ip_address}.");

    // Act
    $this->artisan('monitor:devices')
        ->assertExitCode(0);

    // Assert no readings were stored
    expect(EnergyReading::count())->toBe(0);
});