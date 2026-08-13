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
        // 1. Clean up duplicate User records sharing the same employee_id
        $duplicateEmpIds = DB::table('users')
            ->whereNotNull('employee_id')
            ->select('employee_id', DB::raw('count(*) as count'))
            ->groupBy('employee_id')
            ->having('count', '>', 1)
            ->pluck('employee_id');

        foreach ($duplicateEmpIds as $empId) {
            $userGroup = DB::table('users')
                ->where('employee_id', $empId)
                ->orderBy('id', 'asc')
                ->get();

            // Keep the first user record as the primary user
            $primaryUser = $userGroup->first();
            $duplicateUserIds = $userGroup->slice(1)->pluck('id')->toArray();

            if (! empty($duplicateUserIds)) {
                // Reassign evaluations if any were created under duplicate user accounts
                DB::table('evaluations')
                    ->whereIn('evaluator_id', $duplicateUserIds)
                    ->update(['evaluator_id' => $primaryUser->id]);

                // Delete model_has_roles entries for duplicate users
                DB::table('model_has_roles')
                    ->where('model_type', 'App\\Models\\User')
                    ->whereIn('model_id', $duplicateUserIds)
                    ->delete();

                // Delete duplicate users
                DB::table('users')
                    ->whereIn('id', $duplicateUserIds)
                    ->delete();
            }
        }

        // 2. Clean up duplicate User records sharing the same student_id
        $duplicateStudentIds = DB::table('users')
            ->whereNotNull('student_id')
            ->select('student_id', DB::raw('count(*) as count'))
            ->groupBy('student_id')
            ->having('count', '>', 1)
            ->pluck('student_id');

        foreach ($duplicateStudentIds as $stuId) {
            $userGroup = DB::table('users')
                ->where('student_id', $stuId)
                ->orderBy('id', 'asc')
                ->get();

            $primaryUser = $userGroup->first();
            $duplicateUserIds = $userGroup->slice(1)->pluck('id')->toArray();

            if (! empty($duplicateUserIds)) {
                DB::table('evaluations')
                    ->whereIn('evaluator_id', $duplicateUserIds)
                    ->update(['evaluator_id' => $primaryUser->id]);

                DB::table('model_has_roles')
                    ->where('model_type', 'App\\Models\\User')
                    ->whereIn('model_id', $duplicateUserIds)
                    ->delete();

                DB::table('users')
                    ->whereIn('id', $duplicateUserIds)
                    ->delete();
            }
        }

        // 3. Add unique constraints to users table for employee_id and student_id
        Schema::table('users', function (Blueprint $table) {
            $table->unique('employee_id');
            $table->unique('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_id']);
            $table->dropUnique(['student_id']);
        });
    }
};
