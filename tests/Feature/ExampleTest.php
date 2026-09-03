<?php

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Support\Facades\Cache;

test('welcome page renders successfully with announcement and typewriter tagline', function () {
    $ay = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
        'evaluation_starts_at' => now()->subDay(),
        'evaluation_ends_at' => now()->addDays(5),
    ]);

    Cache::forget('active_semester');

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('TOUCHING HEARTS');
    $response->assertSee('Evaluation is open from');
});

test('welcome page displays closed evaluation announcement when window is closed', function () {
    $ay = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => false,
        'evaluation_starts_at' => now()->subDays(5),
        'evaluation_ends_at' => now()->subHours(6),
    ]);

    Cache::forget('active_semester');

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('Evaluation is closed');
});
