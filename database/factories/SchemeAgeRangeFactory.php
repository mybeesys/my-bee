<?php

namespace Database\Factories;

use App\Models\InsuranceScheme;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SchemeAgeRange;

class SchemeAgeRangeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SchemeAgeRange::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'scheme_id' => function () {
                return InsuranceScheme::all()->random();
            },
            'from' => $this->faker->numberBetween(1, 1),
            'to' => $this->faker->numberBetween(10, 10),
            'price' => $this->faker->numberBetween(1000, 20000)
        ];
    }
}
