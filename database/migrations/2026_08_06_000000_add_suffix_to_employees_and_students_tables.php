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
            if (!Schema::hasColumn('employees', 'suffix')) {
                $table->string('suffix')->nullable()->after('middle_name');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'suffix')) {
                $table->string('suffix')->nullable()->after('middle_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'suffix')) {
                $table->dropColumn('suffix');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'suffix')) {
                $table->dropColumn('suffix');
            }
        });
    }
};
