<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\Pipeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true).' deal',
            'value' => fake()->numberBetween(100000, 5000000),
            'status' => 'open',
        ];
    }

    /**
     * Ensure every deal has a pipeline + stage in its own organization.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Deal $deal) {
            if ($deal->getAttribute('pipeline_id') === null) {
                $pipeline = Pipeline::create([
                    'organization_id' => $deal->organization_id,
                    'name' => 'Sales Pipeline',
                    'is_default' => false,
                ]);
                $stage = $pipeline->stages()->create(['name' => 'New', 'sort_order' => 0]);

                $deal->pipeline_id = $pipeline->id;
                $deal->stage_id = $stage->id;
            }
        });
    }
}
