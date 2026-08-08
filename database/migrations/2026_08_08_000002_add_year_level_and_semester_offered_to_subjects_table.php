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
        Schema::table('subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('subjects', 'year_level')) {
                $table->unsignedInteger('year_level')->nullable()->after('name');
            }
            if (! Schema::hasColumn('subjects', 'semester_offered')) {
                $table->string('semester_offered')->nullable()->after('year_level');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['year_level', 'semester_offered']);
        });
    }
};
