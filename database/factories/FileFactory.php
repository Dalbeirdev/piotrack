<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'organization_id' => Organization::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'org-1/files/'.fake()->uuid(),
            'name' => $name,
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 500000),
        ];
    }
}
