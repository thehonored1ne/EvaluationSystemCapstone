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
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            // 1. Create temporary table with current data
            DB::statement('CREATE TABLE IF NOT EXISTS subjects_temp AS SELECT * FROM subjects');
            Schema::dropIfExists('subjects');

            // 2. Re-create subjects table WITHOUT unique constraint on code
            Schema::create('subjects', function (Blueprint $table) {
                $table->id();
                $table->string('code');
                $table->string('name');
                $table->unsignedInteger('year_level')->nullable();
                $table->string('semester_offered')->nullable();
                $table->text('description')->nullable();
                $table->integer('units')->default(3);
                $table->timestamps();
            });

            // 3. Restore data from temporary table
            DB::statement('INSERT INTO subjects (id, code, name, year_level, semester_offered, description, units, created_at, updated_at) SELECT id, code, name, year_level, semester_offered, description, units, created_at, updated_at FROM subjects_temp');
            Schema::dropIfExists('subjects_temp');

            Schema::enableForeignKeyConstraints();
        } else {
            Schema::table('subjects', function (Blueprint $table) {
                try {
                    $table->dropUnique('subjects_code_unique');
                } catch (Throwable $e) {
                    try {
                        $table->dropUnique(['code']);
                    } catch (Throwable $ex) {
                        // Ignore if index does not exist
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optional
    }
};
