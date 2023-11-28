<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class DataConsistency extends Model
    {
        use \Sushi\Sushi;

        public function getRows()
        {
            $data = [];

            $missingPriceProducts = Product::whereDoesntHave('lastPrice')->get();

            foreach ($missingPriceProducts as $model)
            {
                $data[] = [
                    'item_id' => $model->id,
                    'name' => $model->name,
                    'class' => strtolower(class_basename($model)),
                    'source' => $this->translateModelClass($model),
                    'type' => app()->getLocale() == "en" ? "Item not priced" : "لم يتم إضافة سعر",
                    'details' => " $model->name is not priced",
                ];
            }


            $missingImagesProducts = Product::whereNull('images')->get();

            foreach ($missingImagesProducts as $model)
            {
                $data[] = [
                    'item_id' => $model->id,
                    'name' => $model->name,
                    'class' => strtolower(class_basename($model)),
                    'source' => $this->translateModelClass($model),
                    'type' => app()->getLocale() == "en" ? "Item does not have any images" : "لم يتم إضافة صورة",
                    'details' => " $model->name does not have any images",
                ];
            }

            foreach (Category::all() as $category) {

                if (method_exists($category, 'getTranslation')) {
                    $name_en_trans = $category->getTranslation('name', 'en');
                    $name_ar_trans = $category->getTranslation('name', 'ar');

                    $description_en_trans = $category->getTranslation('description', 'en');
                    $description_ar_trans = $category->getTranslation('description', 'ar');

                    if ($name_en_trans == null or $name_en_trans == '')
                        $data[] = $this->missingTrans($category, 'en', 'name');

                    if ($name_ar_trans == null or $name_ar_trans == '')
                        $data[] = $this->missingTrans($category, 'ar', 'name');

                    if ($description_en_trans == null or $description_en_trans == '')
                        $data[] = $this->missingTrans($category, 'en', 'description');

                    if ($description_ar_trans == null or $description_ar_trans == '')
                        $data[] = $this->missingTrans($category, 'ar', 'description');
                }

            }

            return $data;
        }

        public function missingTrans(Model $model, $lang, $attribute, $name_attribute = "name"): array
        {
            $name = $model->getTranslation($name_attribute, $lang == "en" ? "ar" : "en") . ' with';
            return [
                'item_id' => $model->id,
                'name' => $model->name,
                'class' => strtolower(class_basename($model)),
                'source' => $this->translateModelClass($model),
                'type' => app()->getLocale() == "en" ? "Missing translation" : "ترجمة مفقودة",
                'details' => " $name id ($model->id) is missing $attribute ($lang) translation",
            ];
        }


        public function translateModelClass(Model $model)
        {
            $locale = app()->getLocale();

            $class_base_name = strtolower(class_basename($model));

            $name = ucfirst($class_base_name);

            switch ($class_base_name)
            {
                case "category":
                    {
                        $name = $locale == "en" ? "Category" : "تصنيف";
                        break;
                    }

                case "product":
                    {
                        $name = $locale == "en" ? "Product" : "صنف";
                        break;
                    }
            }

            return $name;
        }

    }
