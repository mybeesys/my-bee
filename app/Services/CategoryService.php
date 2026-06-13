<?php


    namespace App\Services;


    use App\Models\Category;
    use Illuminate\Support\Collection;
    use Illuminate\Support\Facades\Cache;

    class CategoryService extends ModelService
    {

        public function __construct()
        {
            parent::__construct(Category::class);
        }

        public function dropDown()
        {
            return \Cache::rememberForever('categories-dropdown', function () {
                return $this->all()->pluck('name', 'id');
            });
        }

        public function slug($slug): \Illuminate\Database\Eloquent\Collection
        {
            return Cache::remember('category@' . $slug, 18000, function () use ($slug) {
                return Category::with(['children', 'allChildren', 'parent', 'products'])->slug($slug)->first();
            });
        }

        public function subOf($slug): Collection
        {
            return Cache::remember('sub-of-' . $slug, 18000, function () use ($slug) {
                return Category::with(['children', 'allChildren', 'products'])->slug($slug)->first()->children ?? collect();
            });
        }

        public function main(): \Illuminate\Support\Collection
        {
            return Cache::remember('main-categories', 18000, function () {
                return Category::with(['children', 'allChildren', 'products'])->main()->orderBy("order")->get();
            });
        }

        public function allWithAllChildren($cache = false): \Illuminate\Support\Collection
        {
            if ($cache) {
                return Cache::remember('all-with-all-children', 18000, function () {
                    return Category::with('allChildren')->main()->get();
                });
            }

            return Category::with('allChildren')->main()->get();
        }

        public function forProductCategorySelection($cache = false): \Illuminate\Support\Collection
        {
            if ($cache) {
                return Cache::remember('category-drop-down', 18000, function () {
                    return Category::doesntHave('children')->get();
                });
            }

            return Category::doesntHave('children')->get();
        }

        public function productsOfCategories(\Illuminate\Database\Eloquent\Collection $categories)
        {
            $products = new \Illuminate\Database\Eloquent\Collection();
            if ($categories->isEmpty())
                return $products;

            foreach ($categories as $category) {
                $products = $products->merge($category->products, $category->children->pluck('products'))->flatten();
                $products = $products->merge($category->children->pluck('products')->flatten());
            }

            return $products;
        }

        public function load():Collection
        {
            return Cache::remember('categories', 18000, function () {
                return Category::with(['parent', 'children', 'allChildren.products', 'products'])->get();
            });
        }


        public function _trending($limit = null)
        {
            $items = $this->load()->sortBy(function ($cat){
                return $cat->products->count();
            }, SORT_NATURAL, true);

            if ($limit)
                return $items->take($limit);
            return $items;
        }

        public function _main()
        {
            return $this->load()->where('parent', null);
        }

        public function _subOf($slug): Collection
        {
            return $this->load()->firstWhere('slug', $slug)->allChildren ?? new \Illuminate\Database\Eloquent\Collection();
        }

        public function _slug($slug): Category
        {
            return $this->load()->firstWhere('slug', $slug);
        }

        public function clearCache()
        {
            \Cache::forget('categories-dropdown');
            \Cache::forget('category@*');
            \Cache::forget('sub-of-*');
            \Cache::forget('main-categories');
            \Cache::forget('all-with-all-children');
            \Cache::forget('category-drop-down');
            \Cache::forget('categories');
        }

        public function clearMainCategories()
        {
            \Cache::forget('main-categories');
        }

    }