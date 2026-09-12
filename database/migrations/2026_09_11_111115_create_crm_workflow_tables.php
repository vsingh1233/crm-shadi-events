<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('normalized_phone', 30)->nullable()->index();
        });
        DB::table('leads')->select('id', 'phone')->orderBy('id')->chunkById(100, function ($leads) {
            foreach ($leads as $lead) {
                $digits = preg_replace('/\D/', '', $lead->phone ?? '');
                $phone = str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
                DB::table('leads')->where('id', $lead->id)->update(['normalized_phone' => $phone ?: null]);
            }
        });
        Schema::create('lead_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['lead_id', 'user_id']);
        });
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->dateTime('occurred_at');
            $table->text('notes');
            $table->timestamps();
        });
        Schema::create('lead_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('field', 80);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();
        });
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsible_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('due_at')->index();
            $table->text('notes');
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('reminder_queued_at')->nullable();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('lead_changes');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('lead_collaborators');
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('normalized_phone'));
    }
};
