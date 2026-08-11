<?php

namespace Tests\Feature\Security;

use App\Models\AcademicYear;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('global:127.0.0.1');
        RateLimiter::clear('auth:127.0.0.1');
    }

    public function test_global_rate_limiting_blocks_excessive_requests()
    {
        // Simulate 100 requests from IP 127.0.0.1
        for ($i = 0; $i < 100; $i++) {
            $this->call('GET', '/');
        }

        // The 101st request should be blocked with 429
        $response = $this->call('GET', '/');
        $response->assertStatus(429);
    }

    public function test_auth_rate_limiting_blocks_excessive_login_attempts()
    {
        // Simulate 5 attempts on login GET route
        for ($i = 0; $i < 5; $i++) {
            $this->get('/login');
        }

        // The 6th attempt should be rate limited
        $response = $this->get('/login');
        $response->assertStatus(429);
    }

    public function test_evaluation_form_submission_is_throttled_after_50_attempts()
    {
        $user = User::factory()->create();
        $evaluatee = User::factory()->create();

        $this->actingAs($user);

        // Setup academic context
        $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
        $sem = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => '1st Semester',
            'is_active' => true,
            'is_evaluation_open' => true,
            'evaluation_starts_at' => now()->subDay(),
            'evaluation_ends_at' => now()->addDay(),
            'upward_student_max_points' => 50,
            'upward_employee_max_points' => 0,
            'downward_max_points' => 0,
            'peer_max_points' => 0,
            'self_max_points' => 0,
        ]);

        $criterion = EvaluationCriterion::create([
            'evaluation_type' => 'upward_student',
            'name' => 'Teaching Delivery',
            'order' => 1,
            'max_points' => 50.00,
        ]);
        $q = EvaluationQuestion::create([
            'criterion_id' => $criterion->id,
            'question_text' => 'Question 1',
            'order' => 1,
        ]);

        $rateLimitKey = 'submit-evaluation:'.$user->id.':127.0.0.1';
        RateLimiter::clear($rateLimitKey);

        $component = Livewire::test('evaluation-form', [
            'evaluatee' => $evaluatee,
            'evaluationType' => 'upward_student',
        ]);

        // Call submit 50 times (successful submissions)
        for ($i = 0; $i < 50; $i++) {
            $component->set("ratings.{$q->id}", 5)
                ->set('comments', 'Great teacher!')
                ->call('submit')
                ->assertHasNoErrors()
                ->assertSet('retryAfter', 0);
        }

        // The 51st attempt should trigger rate limiting and set retryAfter to ~300
        $component->set('comments', 'Great teacher!')->call('submit');
        $this->assertGreaterThanOrEqual(250, $component->get('retryAfter'));
    }
}
