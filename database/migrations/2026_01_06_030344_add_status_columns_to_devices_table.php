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
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('is_on')->default(false)->after('key');
            $table->decimal('voltage', 8, 2)->nullable()->after('is_on');
            $table->decimal('consumption', 8, 4)->nullable()->after('voltage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['is_on', 'voltage', 'consumption']);
        });
    }
};
