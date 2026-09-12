<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable()->index();

            $table->string('client_location')->nullable();
            $table->string('wedding_location')->nullable();
            $table->date('wedding_start_date')->nullable()->index();
            $table->date('wedding_end_date')->nullable();

            $table->string('source', 50)->default('manual');

            $table->foreignId('owner_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status', 40)->default('new')->index();
            $table->string('temperature', 10)->nullable()->index();
            $table->text('lost_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
