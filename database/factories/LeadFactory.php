<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return ['name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'owner_id' => User::factory(), 'created_by' => User::factory(), 'status' => 'new', 'source' => 'manual'];
    }
}
