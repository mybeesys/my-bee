<?php

    namespace Database\Seeders;

    use App\Events\ProductAdded;
    use App\Models\Category;
    use App\Models\City;
    use App\Models\Product;
    use App\Models\ShippingCoast;
    use App\Models\ShippingCost;
    use App\Models\Unit;
    use Illuminate\Database\Seeder;
    use Symfony\Component\Console\Helper\ProgressBar;
    use Symfony\Component\Console\Output\ConsoleOutput;


    class ProductSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            $data = [
                ['name' => 'Product One', 'category_id' => Category::all()->random()->id, 'small_unit_id' => Unit::all()->random()->id, 'created_at' => now()],
                ['name' => 'Product Two', 'category_id' => Category::all()->random()->id, 'small_unit_id' => Unit::all()->random()->id, 'created_at' => now()],
                ['name' => 'Product Three', 'category_id' => Category::all()->random()->id, 'small_unit_id' => Unit::all()->random()->id, 'created_at' => now()],
            ];

            foreach ($data as $item) {
                Product::firstOrCreate(['name' => $item['name']], $item);
            }
        }
    }
