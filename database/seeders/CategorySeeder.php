<?php

    namespace Database\Seeders;

    use App\Models\Category;
    use App\Traits\SeederHelper;
    use Illuminate\Database\Seeder;
    use Symfony\Component\Console\Helper\ProgressBar;
    use Symfony\Component\Console\Output\ConsoleOutput;

    class CategorySeeder extends Seeder
    {
        use SeederHelper;

        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {

            $data = [
                ['name' => 'Category One', 'slug' => 'category-one', 'created_at' => now()],
                ['name' => 'Category Two', 'slug' => 'category-two', 'created_at' => now()],
                ['name' => 'Category One', 'slug' => 'category-three', 'created_at' => now()],
            ];

            foreach ($data as $item)
            {
                Category::firstOrCreate(['name' => $item['name']], $item);
            }
        }


    }
