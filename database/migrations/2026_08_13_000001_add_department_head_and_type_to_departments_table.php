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
        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'type')) {
                $table->string('type')->default('academic')->after('code');
            }
            if (! Schema::hasColumn('departments', 'department_head_id')) {
                $table->foreignId('department_head_id')->nullable()->after('dean_id')->constrained('employees')->nullOnDelete();
            }
        });

        Schema::table('semesters', function (Blueprint $table) {
            if (! Schema::hasColumn('semesters', 'department_head_max_points')) {
                $table->decimal('department_head_max_points', 8, 2)->default(50.00)->after('program_head_max_points');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'department_head_id')) {
                $table->dropForeign(['department_head_id']);
                $table->dropColumn('department_head_id');
            }
            if (Schema::hasColumn('departments', 'type')) {
                $table->dropColumn('type');
            }
        });

        Schema::table('semesters', function (Blueprint $table) {
            if (Schema::hasColumn('semesters', 'department_head_max_points')) {
                $table->dropColumn('department_head_max_points');
            }
        });
    }
};
