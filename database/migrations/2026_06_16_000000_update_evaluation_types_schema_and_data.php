<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add columns to semesters
        Schema::table('semesters', function (Blueprint $table) {
            $table->decimal('upward_student_max_points', 5, 2)->default(90.00);
            $table->decimal('upward_employee_max_points', 5, 2)->default(50.00);
            $table->decimal('downward_max_points', 5, 2)->default(50.00);
        });

        // 2. Copy data from student_max_points to upward_student_max_points
        DB::table('semesters')->update([
            'upward_student_max_points' => DB::raw('student_max_points'),
        ]);

        // 3. Drop student_max_points
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn('student_max_points');
        });

        // 4. Update criteria and evaluations data
        // First, student -> upward_student
        DB::table('evaluation_criteria')
            ->where('evaluation_type', 'student')
            ->update(['evaluation_type' => 'upward_student']);

        DB::table('evaluations')
            ->where('evaluation_type', 'student')
            ->update(['evaluation_type' => 'upward_student']);

        // Now, migrate Deans/PHs/Staff evaluations previously marked as peer
        $peerEvals = DB::table('evaluations')
            ->where('evaluation_type', 'peer')
            ->get();

        foreach ($peerEvals as $eval) {
            $evaluator = DB::table('users')
                ->leftJoin('employees', 'employees.id', '=', 'users.employee_id')
                ->where('users.id', $eval->evaluator_id)
                ->first();

            $evaluatee = DB::table('users')
                ->leftJoin('employees', 'employees.id', '=', 'users.employee_id')
                ->where('users.id', $eval->evaluatee_id)
                ->first();

            if ($evaluator && $evaluatee && $evaluator->role && $evaluatee->role) {
                $eRole = $evaluator->role;
                $tRole = $evaluatee->role;

                $newType = 'peer';

                if ($eRole === 'dean') {
                    if ($tRole === 'program head') {
                        $newType = 'downward';
                    }
                } elseif ($eRole === 'program head') {
                    if ($tRole === 'faculty') {
                        $newType = 'downward';
                    } elseif ($tRole === 'dean') {
                        $newType = 'upward_employee';
                    }
                } elseif ($eRole === 'faculty') {
                    if ($tRole === 'program head') {
                        $newType = 'upward_employee';
                    }
                } elseif ($eRole === 'staff') {
                    if ($tRole === 'program head') {
                        $newType = 'upward_employee';
                    }
                }

                if ($newType !== 'peer') {
                    DB::table('evaluations')
                        ->where('id', $eval->id)
                        ->update(['evaluation_type' => $newType]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back student_max_points
        Schema::table('semesters', function (Blueprint $table) {
            $table->decimal('student_max_points', 5, 2)->default(90.00);
        });

        // Restore data
        DB::table('semesters')->update([
            'student_max_points' => DB::raw('upward_student_max_points'),
        ]);

        // Drop new columns
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn(['upward_student_max_points', 'upward_employee_max_points', 'downward_max_points']);
        });

        // Revert data updates
        DB::table('evaluation_criteria')
            ->where('evaluation_type', 'upward_student')
            ->update(['evaluation_type' => 'student']);

        DB::table('evaluations')
            ->where('evaluation_type', 'upward_student')
            ->update(['evaluation_type' => 'student']);

        DB::table('evaluations')
            ->whereIn('evaluation_type', ['upward_employee', 'downward'])
            ->update(['evaluation_type' => 'peer']);
    }
};
