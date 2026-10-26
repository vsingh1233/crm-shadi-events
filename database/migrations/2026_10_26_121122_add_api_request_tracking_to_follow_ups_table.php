<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->uuid('api_request_id')->nullable();
            $table->string('api_payload_hash', 64)->nullable();
            $table->unique(['created_by', 'api_request_id']);
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->dropUnique(['created_by', 'api_request_id']);
            $table->dropColumn(['api_request_id', 'api_payload_hash']);
        });
    }
};
