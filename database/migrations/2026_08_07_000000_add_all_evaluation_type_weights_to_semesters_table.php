<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            if (! Schema::hasColumn('semesters', 'dean_max_points')) {
                $table->decimal('dean_max_points', 8, 2)->default(50.00)->after('upward_employee_max_points');
            }
            if (! Schema::hasColumn('semesters', 'program_head_max_points')) {
                $table->decimal('program_head_max_points', 8, 2)->default(50.00)->after('dean_max_points');
            }
            if (! Schema::hasColumn('semesters', 'staff_max_points')) {
                $table->decimal('staff_max_points', 8, 2)->default(10.00)->after('self_max_points');
            }
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn(['dean_max_points', 'program_head_max_points', 'staff_max_points']);
        });
    }
};
