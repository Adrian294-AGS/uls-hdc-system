<?php

namespace Database\Factories;

use App\Enums\IntakeStatus;
use App\Models\IntakeInterview;
use App\Models\User;
use App\Support\Form8;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntakeInterview>
 */
class IntakeInterviewFactory extends Factory
{
    /**
     * Define the model's default state: a draft with every Yes/No answered "No".
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $attributes = [
            'student_id' => User::factory()->student(),
            'counselor_id' => User::factory()->counselor(),
            'date_of_entry' => now()->format('Y-m-d'),
            'name' => fake()->name(),
            'course' => 'BSIT',
            'age' => fake()->numberBetween(17, 25),
            'sex' => fake()->randomElement(['male', 'female']),
            'civil_status' => 'single',
            'citizenship' => 'filipino',
            'client_description' => fake()->sentence(),
            'presenting_concerns' => fake()->sentence(),
            'conceptualization' => fake()->sentence(),
            'recommendations' => fake()->sentence(),
            'relationship_during_intake' => ['cooperative'],
        ];

        foreach (Form8::yesNoFields() as $field) {
            $attributes[$field] = false;
        }

        return $attributes;
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IntakeStatus::Finalized,
            'interview_at' => now()->subDay(),
            'interviewee_acknowledged' => true,
            'finalized_at' => now(),
        ]);
    }

    /**
     * Answer "Yes" to a follow-up concern (attempted suicide by default).
     */
    public function needingFollowUp(string $concern = 'attempted_suicide'): static
    {
        return $this->state(fn (array $attributes) => [$concern => true]);
    }
}
