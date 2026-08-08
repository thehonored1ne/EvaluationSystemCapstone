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
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "2025-2026"
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->string('name'); // e.g. "1st Semester", "2nd Semester", "Summer"
            $table->boolean('is_active')->default(false);
            $table->boolean('is_evaluation_open')->default(false);
            $table->decimal('student_max_points', 5, 2)->default(90.00);
            $table->decimal('peer_max_points', 5, 2)->default(50.00);
            $table->decimal('self_max_points', 5, 2)->default(10.00);
            $table->timestamp('evaluation_starts_at')->nullable();
            $table->timestamp('evaluation_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code'); // e.g. "CS101"
            $table->string('name'); // e.g. "Introduction to Computer Science"
            $table->unsignedInteger('year_level')->nullable(); // e.g. 1, 2, 3, 4
            $table->string('semester_offered')->nullable(); // e.g. "1st Semester", "2nd Semester", "Summer", "Both"
            $table->text('description')->nullable();
            $table->integer('units')->default(3);
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('employees')->onDelete('cascade'); // The professor/faculty
            $table->string('section'); // e.g. "BSCS-3A"
            $table->string('schedule')->nullable(); // e.g. "Mon/Wed 9:00 AM - 10:30 AM"
            $table->string('room')->nullable(); // e.g. "Lab 1"
            $table->timestamps();
        });

        Schema::create('class_student', function (Blueprint $table) {
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->primary(['class_id', 'student_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_student');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_years');
    }
};
