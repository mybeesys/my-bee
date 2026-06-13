<?php

    namespace Database\Seeders;


    use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\Artisan;
    use Illuminate\Support\Facades\DB;


    class DatabaseSeeder extends Seeder
    {
        /**
         * Seed the application's database.
         *
         * @return void
         */
        public function run()
        {
            Artisan::call("optimize:clear");

            $this->call(SettingsSeeder::class);
            $this->call(CountrySeeder::class);
            $this->call(StateSeeder::class);
            $this->call(CitySeeder::class);
            $this->call(AreaSeeder::class);

//            $this->call(UnitSeeder::class);
//            $this->call(AccSeeder::class);
//
//            $this->call(ClientSeeder::class);
//            $this->call(SupplierSeeder::class);
//            $this->call(RepresentativeSeeder::class);
//
//            $this->call(ContractingProjectStatusSeeder::class);
//
//            $this->call(CurrencySeeder::class);
//            $this->call(InvoiceStatusSeeder::class);
//
//            $this->call(CategorySeeder::class);
//            $this->call(ProductSeeder::class);
//            $this->call(WarehouseSeeder::class);
//
//            $this->call(DeductionTypeSeeder::class);
//            $this->call(DepartmentSeeder::class);
//            $this->call(FunctionalClassSeeder::class);
//            $this->call(JobSeeder::class);
//            $this->call(QualificationTypeSeeder::class);
//            $this->call(VacationTypeSeeder::class);
//            $this->call(EntitlementSeeder::class);

            $this->call(UserSeeder::class);

            $this->call(PlanSeeder::class);

        }
    }
