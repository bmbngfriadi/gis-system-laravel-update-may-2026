<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_receives', function (Blueprint $table) {
            $table->id();
            $table->string('gr_id')->unique();
            $table->string('erp_gr_no')->nullable();
            $table->string('username');
            $table->string('fullname');
            $table->text('remarks');
            $table->string('gr_photo')->nullable();
            $table->json('items_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_receives');
    }
};
