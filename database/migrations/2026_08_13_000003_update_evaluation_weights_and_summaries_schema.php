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
        // 1. Add overall max points & percentage weights to semesters table
        Schema::table('semesters', function (Blueprint $table) {
            $table->decimal('overall_max_points', 8, 2)->default(200.00)->after('is_evaluation_open');
            $table->decimal('student_weight', 5, 2)->default(30.00)->after('overall_max_points');
            $table->decimal('dean_weight', 5, 2)->default(15.00)->after('student_weight');
            $table->decimal('ph_dh_weight', 5, 2)->default(15.00)->after('dean_weight');
            $table->decimal('peer_weight', 5, 2)->default(15.00)->after('ph_dh_weight');
            $table->decimal('self_weight', 5, 2)->default(5.00)->after('peer_weight');
            $table->decimal('superior_weight', 5, 2)->default(20.00)->after('self_weight');
        });

        // 2. Add score tracking columns to evaluations table
        Schema::table('evaluations', function (Blueprint $table) {
            $table->decimal('raw_score', 8, 2)->nullable()->after('rating_average');
            $table->decimal('max_score', 8, 2)->nullable()->after('raw_score');
            $table->decimal('weighted_score', 8, 2)->nullable()->after('max_score');
        });

        // 3. Create evaluation_summaries table for pre-calculated reporting & fast exports
        Schema::create('evaluation_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluatee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();

            $table->decimal('student_score', 8, 2)->nullable();
            $table->decimal('dean_score', 8, 2)->nullable();
            $table->decimal('ph_dh_score', 8, 2)->nullable();
            $table->decimal('peer_score', 8, 2)->nullable();
            $table->decimal('self_score', 8, 2)->nullable();
            $table->decimal('superior_score', 8, 2)->nullable();

            $table->decimal('overall_rating', 8, 2)->default(0.00);
            $table->integer('total_submissions')->default(0);

            $table->timestamps();

            $table->unique(['evaluatee_id', 'semester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_summaries');

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn(['raw_score', 'max_score', 'weighted_score']);
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn([
                'overall_max_points',
                'student_weight',
                'dean_weight',
                'ph_dh_weight',
                'peer_weight',
                'self_weight',
                'superior_weight',
            ]);
        });
    }
};
