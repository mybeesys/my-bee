<?php


    namespace App\Services;


    use Illuminate\Database\Eloquent\Collection;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Query\Builder;
    use Illuminate\Support\Facades\Cache;

    class ModelService
    {

        protected $modelClass;
        protected $modelAattributes = [];
        protected $relations = [];
        public $useCache = true;
        public $cacheTTL = 18000;

        public $items;
        public function __construct($modelClass)
        {
            $this->modelClass = $modelClass;

            $model = app()->make($this->modelClass);

            if (!$model instanceof Model) {
                throw new \Exception("Class {$this->modelClass} must be an instance of Illuminate\\Database\\Eloquent\\Model");
            }

            $this->modelAattributes = $model->getAttributes();
        }

//    public function load():Collection
//    {
//        if($this->useCache){
//            return Cache::remember('_all_data@'.get_class($this->modelClass), $this->cacheTTL, function (){
//                return $this->all();
//            });
//        }else{
//            return $this->all();
//        }
//    }

        public function only(array $fields)
        {
            $this->only = $fields;
        }

        public function modelClass($modelClass)
        {
            $this->modelClass = $modelClass;

            $model = app()->make($this->modelClass);

            if (!$model instanceof Model) {
                throw new \Exception("Class {$this->modelClass} must be an instance of Illuminate\\Database\\Eloquent\\Model");
            }

            $this->modelAattributes = $model->getAttributes();
        }

        public function __relations(array $relations)
        {
            $this->relations = $relations;
            return $this;
        }

        public function create($input):Model
        {
            return ($this->modelClass)::create($input);
        }

        public function update($input, $id)
        {
            return ($this->modelClass)::update($input, $id);
        }

        public function delete($id)
        {
            return ($this->modelClass)::findOrFail($id)->delete();
        }

        public function find($id): Model
        {
            return ($this->modelClass)::findOrFail($id);
        }

        public function findOrFail($id): Model
        {
            return ($this->modelClass)::findOrFail($id);
        }

        public function all(): Collection
        {
            return ($this->modelClass)::with($this->relations)->get();
        }

        public function latest($limit = null): Collection
        {
            return $limit ? ($this->modelClass)::with($this->relations)->take($limit)->get() : ($this->modelClass)::with($this->relations)->get();;
        }

        public function paginate($paginate = 10): Collection
        {
            return ($this->modelClass)::with($this->relations)->paginate($paginate);
        }

        public function query(): \Illuminate\Database\Eloquent\Builder
        {
            return ($this->modelClass)::query();
        }
    }