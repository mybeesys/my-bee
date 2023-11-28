<?php

namespace Database\Factories;

use App\Models\InsuranceCompany;
use App\Models\InsuranceScheme;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Model;

class InsuranceSchemeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = InsuranceScheme::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => [
                'en' => 'name_en: ' . $this->faker->name(),
                'ar' => 'name_ar: ' . $this->faker->name(),
            ],
            'insurance_company_id' => function () {
                return InsuranceCompany::all()->random();
            },
            'price' => $this->faker->numberBetween(100, 10000),
            'term' => $this->faker->name(),
            'description' => $this->faker->name(),
        ];
    }
}
