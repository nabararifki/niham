<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                  => fake()->name(),
            'username'              => fake()->unique()->userName(),
            'role_id'               => \App\Models\Role::factory(),
            'department_id'         => \App\Models\Department::factory(),
            'property_id'           => null,
            'email'                 => fake()->unique()->safeEmail(),
            'email_verified_at'     => now(),
            'password'              => static::$password ??= Hash::make('password'),
            'remember_token'        => Str::random(10),
            // Explicitly set DB-defaulted columns so the in-memory model instance
            // has them in $attributes. shouldBeStrict() throws MissingAttributeException
            // for any attribute accessed that was not present in $attributes at creation.
            'is_super_admin'        => false,
            'notify_department'     => true,
            'notify_all_properties' => false,
            'notify_email'          => false,
            'email_frequency'       => 'immediate',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
