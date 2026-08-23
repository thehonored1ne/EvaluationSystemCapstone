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
        Schema::table('employees', function (Blueprint $table) {
            $table->index(['role', 'status', 'department_id'], 'employees_role_status_dept_idx');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->index(['semester_id', 'evaluation_type'], 'evaluations_sem_type_idx');
            $table->index(['semester_id', 'evaluator_id'], 'evaluations_sem_evaluator_idx');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->index(['semester_id', 'teacher_id'], 'classes_sem_teacher_idx');
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->index(['is_active', 'is_evaluation_open'], 'semesters_active_open_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_role_status_dept_idx');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex('evaluations_sem_type_idx');
            $table->dropIndex('evaluations_sem_evaluator_idx');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('classes_sem_teacher_idx');
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->dropIndex('semesters_active_open_idx');
        });
    }
};
