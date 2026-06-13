<?php

namespace App\Traits;

use App\Jobs\EvaluateModelEventForWorkflow;
use Illuminate\Database\Eloquent\Model;

trait TrackWorkflowModelEvents
{
    public static function bootTrackWorkflowModelEvents()
    {
//        static::created(function (Model $model) {
//            dispatch(new EvaluateModelEventForWorkflow($model, 'created'));
//        });
//
//        static::updated(function (Model $model) {
//            dispatch(new EvaluateModelEventForWorkflow($model, 'updated', $model->getChanges()));
//        });
//
//        static::deleted(function (Model $model) {
//            dispatch(new EvaluateModelEventForWorkflow($model, 'deleted'));
//        });
    }
}
