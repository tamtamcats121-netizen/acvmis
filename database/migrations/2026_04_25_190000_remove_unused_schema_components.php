<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            if (Schema::hasColumn('user_settings', 'notification_in_app_enabled')) {
                $table->dropColumn('notification_in_app_enabled');
            }

            if (Schema::hasColumn('user_settings', 'language')) {
                $table->dropColumn('language');
            }

            if (Schema::hasColumn('user_settings', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });

        Schema::table('wellness_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('wellness_attachments', 'file_type')) {
                $table->dropColumn('file_type');
            }
        });

        Schema::table('academic_document_ocr_runs', function (Blueprint $table) {
            if (Schema::hasColumn('academic_document_ocr_runs', 'extracted_student_name')) {
                $table->dropColumn('extracted_student_name');
            }

            if (Schema::hasColumn('academic_document_ocr_runs', 'extracted_student_id_number')) {
                $table->dropColumn('extracted_student_id_number');
            }

            if (Schema::hasColumn('academic_document_ocr_runs', 'extracted_academic_period_label')) {
                $table->dropColumn('extracted_academic_period_label');
            }
        });

        Schema::dropIfExists('academic_document_parsed_subjects');

        Schema::table('sports', function (Blueprint $table) {
            if (Schema::hasColumn('sports', 'description')) {
                $table->dropColumn('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('user_settings', 'notification_in_app_enabled')) {
                $table->boolean('notification_in_app_enabled')->default(true)->after('notification_email_enabled');
            }

            if (!Schema::hasColumn('user_settings', 'language')) {
                $table->string('language', 12)->default('en')->after('notification_email_enabled');
            }

            if (!Schema::hasColumn('user_settings', 'timezone')) {
                $table->string('timezone', 60)->default('Asia/Manila')->after('notification_in_app_enabled');
            }
        });

        Schema::table('wellness_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('wellness_attachments', 'file_type')) {
                $table->string('file_type', 100)->nullable()->after('file_path');
            }
        });

        Schema::table('academic_document_ocr_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('academic_document_ocr_runs', 'extracted_student_name')) {
                $table->string('extracted_student_name')->nullable()->after('raw_text');
            }

            if (!Schema::hasColumn('academic_document_ocr_runs', 'extracted_student_id_number')) {
                $table->string('extracted_student_id_number', 50)->nullable()->after('extracted_student_name');
            }

            if (!Schema::hasColumn('academic_document_ocr_runs', 'extracted_academic_period_label')) {
                $table->string('extracted_academic_period_label', 120)->nullable()->after('extracted_student_id_number');
            }
        });

        if (!Schema::hasTable('academic_document_parsed_subjects')) {
            Schema::create('academic_document_parsed_subjects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_document_ocr_run_id')
                    ->constrained('academic_document_ocr_runs', 'id', 'academic_doc_parsed_subjects_run_fk')
                    ->cascadeOnDelete();
                $table->unsignedInteger('row_number');
                $table->string('subject_code_raw', 50)->nullable();
                $table->string('subject_name_raw', 150);
                $table->decimal('units', 6, 2)->nullable();
                $table->decimal('grade_value', 4, 2)->nullable();
                $table->string('remarks_raw', 50)->nullable();
                $table->decimal('row_confidence', 5, 2)->nullable();
                $table->boolean('is_flagged')->default(false);
                $table->timestamps();

                $table->unique(['academic_document_ocr_run_id', 'row_number'], 'academic_document_subjects_run_row_unique');
                $table->index(['academic_document_ocr_run_id', 'is_flagged'], 'academic_document_subjects_run_flagged_index');
            });
        }

        Schema::table('sports', function (Blueprint $table) {
            if (!Schema::hasColumn('sports', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });
    }
};
