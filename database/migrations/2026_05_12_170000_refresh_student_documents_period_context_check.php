<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_CONSTRAINT = 'academic_documents_period_context_check';
    private const NEW_CONSTRAINT = 'student_documents_period_context_check';

    public function up(): void
    {
        $documentsTable = $this->documentsTable();
        $typesTable = $this->documentTypesTable();

        if (!$documentsTable || !$typesTable) {
            return;
        }

        $this->refreshConstraint($documentsTable, $typesTable);
    }

    public function down(): void
    {
        $documentsTable = $this->documentsTable();

        if (!$documentsTable) {
            return;
        }

        $this->dropConstraint($documentsTable, self::NEW_CONSTRAINT);
    }

    private function refreshConstraint(string $documentsTable, string $typesTable): void
    {
        $registrationTypeIds = DB::table($typesTable)
            ->where('context', 'registration')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $periodSubmissionTypeIds = DB::table($typesTable)
            ->where('context', 'period_submission')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $this->dropConstraint($documentsTable, self::OLD_CONSTRAINT);
        $this->dropConstraint($documentsTable, self::NEW_CONSTRAINT);

        if ($registrationTypeIds->isEmpty() || $periodSubmissionTypeIds->isEmpty()) {
            return;
        }

        $registrationList = $registrationTypeIds->implode(', ');
        $periodSubmissionList = $periodSubmissionTypeIds->implode(', ');
        $expression = "((academic_period_id IS NULL AND document_type_id IN ({$registrationList})) OR (academic_period_id IS NOT NULL AND document_type_id IN ({$periodSubmissionList})))";

        DB::statement("ALTER TABLE {$documentsTable} ADD CONSTRAINT " . self::NEW_CONSTRAINT . " CHECK {$expression}");
    }

    private function dropConstraint(string $table, string $constraint): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$constraint}");
            } catch (Throwable) {
                try {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
                } catch (Throwable) {
                    // The constraint may not exist on partially migrated environments.
                }
            }

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        }
    }

    private function documentsTable(): ?string
    {
        if (Schema::hasTable('student_documents')) {
            return 'student_documents';
        }

        return Schema::hasTable('academic_documents') ? 'academic_documents' : null;
    }

    private function documentTypesTable(): ?string
    {
        if (Schema::hasTable('document_types')) {
            return 'document_types';
        }

        return Schema::hasTable('academic_document_types') ? 'academic_document_types' : null;
    }
};
