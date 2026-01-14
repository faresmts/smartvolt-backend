<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('energy_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->onDelete('cascade');
            $table->decimal('kwh_consumption', 10, 4); // Storing kWh consumption
            $table->boolean('relay_status');
            $table->decimal('voltage_rms', 8, 2)->nullable();
            $table->decimal('current_rms', 8, 4)->nullable();
            $table->decimal('power', 10, 4)->nullable(); // Instantaneous power
            $table->decimal('energy', 10, 4)->nullable(); // Accumulated energy (raw from device)
            $table->decimal('cost', 10, 4)->nullable(); // Accumulated cost (raw from device)
            $table->timestamp('recorded_at')->useCurrent(); // Timestamp from when data was recorded
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('energy_readings');
    }
};
