<?php

    namespace App\Filament\Tenant\Resources\CategoryResource\Widgets;

    use App\Models\Category;
    use Filament\Widgets\StatsOverviewWidget as BaseWidget;
    use Filament\Widgets\StatsOverviewWidget\Card;

    class CategoryOverview extends BaseWidget
    {

        public function getCards(): array
        {
            return [
//                Card::make(__('fields.main_categories'), Category::main()->count())
//                    ->color('primary'),
//                Card::make(__('fields.all_categories'), Category::count())
//                    ->color('success'),
            ];
        }

    }
