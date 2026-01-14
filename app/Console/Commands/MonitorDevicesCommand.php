<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\EnergyReading;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorDevicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:devices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor smart devices and store their energy readings.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting device monitoring...');

        $devices = Device::all(); // Get all devices registered in the system

        Log::info('Olha ele ai rodando');

        foreach ($devices as $device) {
            if (! $device->ip_address) {
                Log::warning("Device {$device->id} has no IP address configured. Skipping monitoring.");
                continue;
            }

            $client = new Client(['base_uri' => $device->ip_address]);

            try {
                $response = $client->get('api/status');
                $statusData = json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error("Failed to parse JSON response for device {$device->id} at {$device->ip_address}.");
                    continue;
                }

                // Store data in EnergyReading model
                EnergyReading::create([
                    'device_id' => $device->id,
                    'kwh_consumption' => $statusData['energy'] ?? 0, // Assuming 'energy' is in kWh
                    'relay_status' => (bool) ($statusData['relay_state'] ?? false),
                    'voltage_rms' => $statusData['voltage_rms'] ?? null,
                    'current_rms' => $statusData['current_rms'] ?? null,
                    'power' => $statusData['power'] ?? null,
                    'energy' => $statusData['energy'] ?? null, // Raw accumulated energy from device
                    'cost' => $statusData['cost'] ?? null, // Raw accumulated cost from device
                    'recorded_at' => now(),
                ]);

                // Update device's is_on status locally
                $device->update(['is_on' => (bool) ($statusData['relay_state'] ?? false)]);

                $this->info("Successfully monitored device {$device->id} at {$device->ip_address}.");

            } catch (RequestException $e) {
                Log::error("Failed to get status for device {$device->id} at {$device->ip_address}: ".$e->getMessage());
                $this->error("Failed to monitor device {$device->id}. Check logs for details.");
            } catch (\Exception $e) {
                Log::error("An unexpected error occurred while monitoring device {$device->id}: ".$e->getMessage());
                $this->error("An unexpected error occurred for device {$device->id}. Check logs for details.");
            }
        }

        $this->info('Device monitoring completed.');
    }
}
