<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gis_requests', function (Blueprint $table) {
            $table->string('app_planthead')->nullable()->after('head_time');
            $table->timestamp('planthead_time')->nullable()->after('app_planthead');
        });
    }

    public function down(): void
    {
        Schema::table('gis_requests', function (Blueprint $table) {
            $table->dropColumn(['app_planthead', 'planthead_time']);
        });
    }
};
