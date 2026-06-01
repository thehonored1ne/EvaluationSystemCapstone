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
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('evaluation_type'); // 'student', 'peer', 'self'
            $table->string('name'); // e.g. "Classroom Management"
            $table->integer('order')->default(0);
            $table->decimal('max_points', 5, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('evaluation_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_id')->constrained('evaluation_criteria')->onDelete('cascade');
            $table->text('question_text');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('evaluatee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->foreignId('class_id')->nullable()->constrained('classes')->onDelete('cascade');
            $table->string('evaluation_type'); // 'student', 'peer', 'self'
            $table->decimal('rating_average', 5, 2)->default(0.00);
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['semester_id', 'evaluator_id', 'evaluatee_id', 'class_id'], 'evaluations_unique_submission');
        });

        Schema::create('evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('evaluation_questions')->onDelete('cascade');
            $table->integer('rating'); // e.g., 1 to 5
            $table->timestamps();

            $table->unique(['evaluation_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_answers');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('evaluation_questions');
        Schema::dropIfExists('evaluation_criteria');
    }
};
