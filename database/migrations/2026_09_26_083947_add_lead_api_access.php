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
         Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_create_leads_via_api')->default(false);
            $table->char('lead_api_token_hash', 64)->nullable()->unique();
            $table->timestamp('lead_api_token_expires_at')->nullable();
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->uuid('api_request_id')->nullable();
            $table->char('api_payload_hash', 64)->nullable();

            $table->unique(
                ['created_by', 'api_request_id'],
                'leads_api_request_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
         Schema::table('leads', function (Blueprint $table): void {
            $table->dropUnique('leads_api_request_unique');
            $table->dropColumn(['api_request_id', 'api_payload_hash']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['lead_api_token_hash']);
            $table->dropColumn([
                'can_create_leads_via_api',
                'lead_api_token_hash',
                'lead_api_token_expires_at',
            ]);
        });
    }
};
