<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('academic_holds');
        Schema::dropIfExists('academic_period_messages');
    }

    public function down(): void
    {
        if (!Schema::hasTable('academic_holds')) {
            Schema::create('academic_holds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('source_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
                $table->enum('reason', ['missing_submissions', 'legacy_student_status', 'manual_hold']);
                $table->enum('status', ['suspended', 'unenrolled', 'resolved']);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['student_id', 'status'], 'academic_holds_student_status_index');
            });
        }

        if (!Schema::hasTable('academic_period_messages')) {
            Schema::create('academic_period_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
                $table->text('message');
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['academic_period_id', 'published_at'], 'academic_period_messages_period_published_idx');
            });
        }
    }
};
