<?php


    namespace App\Services;


    use App\Helpers\CacheManager;
    use App\Models\State;
    use Illuminate\Support\Facades\Cache;

    class StateService extends ModelService
    {
        public function __construct()
        {
            parent::__construct(State::class);
        }

        public function load()
        {
            return Cache::remember('states', 18000, function (){
                return $this->__relations(['localities'])->all();
            });
        }

        public function clearCache()
        {
        }
    }