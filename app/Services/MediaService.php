<?php

    namespace App\Services;


    class MediaService
    {
        public static function url($dir, $image)
        {
            return \Storage::disk('public')->url($dir . "/" . basename($image ?? ""));
        }

        public function exists($path, $disk = "public"):bool
        {
            return \Storage::disk($disk)->exists($path);

        }

        public function delete($path, $disk = "public"):bool
        {
            return \Storage::disk($disk)->delete($path);
        }

        public function brokenLinks($type = "products")
        {

            $broken = [];

            switch ($type)
            {
                case 'products' :{
                    $whiteListProducts = array_column(\App\Models\Product::pluck('images')->toArray(), 0);
                    $whiteListBundles = array_column(\App\Models\ProductBundle::pluck('images')->toArray(), 0);
                    $whiteListVariants = array_column(\App\Models\ProductVariant::pluck('images')->toArray(), 0);

                    $whiteListProducts = array_merge($whiteListBundles, $whiteListVariants);

                    foreach (\Storage::disk('public')->files('products') as $file)
                    {
                        if(!in_array($file, $whiteListProducts))
                            $broken[] = self::url('products', $file);
                    }

                    break;
                }
                case "categories":
                    {
                        $whiteListCategories = array_column(\App\Models\Category::pluck('image')->toArray(), 0);

                        foreach (\Storage::disk('public')->files('categories') as $file)
                        {
                            if(!in_array($file, $whiteListCategories))
                                $broken[] = self::url('categories', $file);
                        }
                        break;

                    }
                case "banners":
                    {
                        $whiteListBanners = array_column(\App\Models\Banner::pluck('image')->toArray(), 0);

                        foreach (\Storage::disk('public')->files('banners') as $file)
                        {
                            if(!in_array($file, $whiteListBanners))
                                $broken[] = self::url('banners', $file);
                        }
                        break;

                    }
                case "brands":
                    {
                        $whiteListBrands = array_column(\App\Models\Brand::pluck('image')->toArray(), 0);

                        foreach (\Storage::disk('public')->files('brands') as $file)
                        {
                            if(!in_array($file, $whiteListBrands))
                                $broken[] = self::url('brands', $file);
                        }
                        break;

                    }
                case "expenses":
                    {
                        $whiteListExpenses = array_column(\App\Models\Expense::pluck('receipts')->toArray(), 0);

                        foreach (\Storage::disk('public')->files('purchases-receipts') as $file)
                        {
                            if(!in_array($file, $whiteListExpenses))
                                $broken[] = self::url('purchases-receipts', $file);
                        }
                        break;

                    }
                case "alerts":
                    {
                        $whiteListAlerts = array_column(\App\Models\InAppAlert::pluck('images')->toArray(), 0);

                        foreach (\Storage::disk('public')->files('in-app-alerts') as $file)
                        {
                            if(!in_array($file, $whiteListAlerts))
                                $broken[] = self::url('in-app-alerts', $file);
                        }
                        break;

                    }
            }

            return $broken;
        }
    }