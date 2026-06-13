<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */

    protected $model = Product::class;

    public function definition()
    {
        $title = $this->faker->word;
        return [
            'title' => [
                'en' => $title,
                'ar' => $title,
            ],
            'type' => 'basic',
            'description' => $this->faker->paragraph,
            'city_id' => City::all()->random()->id,
            'category_id' => Category::with(['children'])->canListProduct()->get()->random()->id,
            'vendor_id' => User::vendor()->get()->random()->id,
            'featured' => rand(0, 1),
            'views' => rand(1, 100000),
        ];
    }
}
