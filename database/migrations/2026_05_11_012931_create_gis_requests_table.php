<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_requests', function (Blueprint $table) {
            $table->id();
            $table->string('req_id')->unique();
            $table->string('erp_gi_no')->nullable();
            $table->string('username');
            $table->string('fullname');
            $table->string('department');
            $table->string('section')->nullable();
            $table->text('purpose');
            $table->json('items_json');
            $table->string('status')->default('Pending Head');
            $table->string('app_head')->nullable();
            $table->timestamp('head_time')->nullable();
            $table->string('app_wh')->nullable();
            $table->timestamp('wh_time')->nullable();
            $table->string('received_by')->nullable();
            $table->timestamp('receive_time')->nullable();
            $table->string('issue_photo')->nullable();
            $table->string('receive_photo')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_requests');
    }
};
