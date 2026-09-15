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
        // 1. Clean up duplicate academic_years sharing the same name
        $duplicateYearNames = DB::table('academic_years')
            ->select('name', DB::raw('count(*) as count'))
            ->groupBy('name')
            ->having('count', '>', 1)
            ->pluck('name');

        foreach ($duplicateYearNames as $name) {
            $yearGroup = DB::table('academic_years')
                ->where('name', $name)
                ->orderByDesc('is_active')
                ->orderBy('id', 'asc')
                ->get();

            // Keep the active or lowest ID year as the canonical record
            $primaryYear = $yearGroup->first();
            $duplicateYearIds = $yearGroup->slice(1)->pluck('id')->toArray();

            if (! empty($duplicateYearIds)) {
                // Reassign child semesters pointing to duplicate academic years
                DB::table('semesters')
                    ->whereIn('academic_year_id', $duplicateYearIds)
                    ->update(['academic_year_id' => $primaryYear->id]);

                // Delete duplicate academic year records
                DB::table('academic_years')
                    ->whereIn('id', $duplicateYearIds)
                    ->delete();
            }
        }

        // 2. Clean up duplicate semesters sharing the same academic_year_id and name
        $duplicateSemKeys = DB::table('semesters')
            ->select('academic_year_id', 'name', DB::raw('count(*) as count'))
            ->groupBy('academic_year_id', 'name')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicateSemKeys as $dup) {
            $semGroup = DB::table('semesters')
                ->where('academic_year_id', $dup->academic_year_id)
                ->where('name', $dup->name)
                ->orderByDesc('is_active')
                ->orderBy('id', 'asc')
                ->get();

            // Choose primary: prioritize the semester that already has associated classes or evaluations
            $primarySem = null;
            foreach ($semGroup as $candidate) {
                $hasClasses = DB::table('classes')->where('semester_id', $candidate->id)->exists();
                $hasEvals = DB::table('evaluations')->where('semester_id', $candidate->id)->exists();
                if ($hasClasses || $hasEvals) {
                    $primarySem = $candidate;
                    break;
                }
            }

            if (! $primarySem) {
                $primarySem = $semGroup->first();
            }

            $duplicateSemIds = $semGroup->where('id', '!=', $primarySem->id)->pluck('id')->toArray();

            if (! empty($duplicateSemIds)) {
                // Reassign academic classes to the canonical semester
                DB::table('classes')
                    ->whereIn('semester_id', $duplicateSemIds)
                    ->update(['semester_id' => $primarySem->id]);

                // Reassign evaluations to the canonical semester
                DB::table('evaluations')
                    ->whereIn('semester_id', $duplicateSemIds)
                    ->update(['semester_id' => $primarySem->id]);

                // Reassign evaluation summaries if the table exists
                if (Schema::hasTable('evaluation_summaries')) {
                    DB::table('evaluation_summaries')
                        ->whereIn('semester_id', $duplicateSemIds)
                        ->update(['semester_id' => $primarySem->id]);
                }

                // Delete duplicate semester records
                DB::table('semesters')
                    ->whereIn('id', $duplicateSemIds)
                    ->delete();
            }
        }

        // 3. Ensure strictly ONE active semester and ONE active academic year
        $activeSemester = DB::table('semesters')->where('is_active', true)->orderByDesc('id')->first();
        if ($activeSemester) {
            DB::table('semesters')
                ->where('id', '!=', $activeSemester->id)
                ->update(['is_active' => false]);

            DB::table('academic_years')
                ->where('id', $activeSemester->academic_year_id)
                ->update(['is_active' => true]);

            DB::table('academic_years')
                ->where('id', '!=', $activeSemester->academic_year_id)
                ->update(['is_active' => false]);
        }

        // 4. Add unique constraints to physically prevent future duplicates
        Schema::table('academic_years', function (Blueprint $table) {
            $table->unique('name', 'academic_years_name_unique');
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->unique(['academic_year_id', 'name'], 'semesters_ay_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropUnique('semesters_ay_name_unique');
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropUnique('academic_years_name_unique');
        });
    }
};
