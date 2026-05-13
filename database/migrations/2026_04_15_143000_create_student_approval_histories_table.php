<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('coaches')->nullOnDelete();
            $table->enum('decision', ['approved', 'rejected']);
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
        });

        $rows = DB::table('account_approvals as aa')
            ->join('users', 'users.id', '=', 'aa.user_id')
            ->join('students', 'students.user_id', '=', 'users.id')
            ->leftJoin('coaches as c', 'c.user_id', '=', 'aa.admin_id')
            ->whereIn('aa.decision', ['approved', 'rejected'])
            ->select('students.id as student_id', 'c.id as coach_id', 'aa.decision', 'aa.remarks', 'aa.created_at', 'aa.updated_at')
            ->get();

        foreach ($rows as $row) {
            DB::table('student_approval_histories')->insert([
                'student_id' => $row->student_id,
                'coach_id' => $row->coach_id,
                'decision' => $row->decision,
                'remarks' => $row->remarks,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_approval_histories');
    }
};
