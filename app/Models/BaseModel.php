<?php

namespace App\Models;

use App\Traits\TrackWorkflowModelEvents;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BaseModel extends Model
{
    use LogsActivity, TrackWorkflowModelEvents;


//    public
    // l jS \of F Y h:i:s A      Prints something like: Monday 8th of August 2005 03:12:46 PM
    // F j, Y, g:i a             March 10, 2001, 5:16 pm

    //protected $dateFormat = 'F j, Y, g:i a';

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeCurrentClient(Builder $builder): Builder
    {
        $client = get_client();

        if (! $client) {
            return $builder->whereRaw('1 = 0');
        }

        return $builder->whereRelation('tenant', 'client_id', $client->id);
    }

    public function scopeMine($query)
    {
        return $query->where('user_id', auth()->id());
    }

    public function scopeForUser($query, $user_id)
    {
        return $query->where('user_id', $user_id);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function scopeInactive($query)
    {
        return $query->where('active', 0);
    }

    public function scopePopular($query)
    {
        // By defining the conditions to be a popular book,
        // it's easy to change them later on for all queries at once
        return $query->whereHas('votes', '>=', 10);
    }

    public function scopeCreatedAfter($query, $date)
    {
        return $query->whereDate('created_at', '>', $date);
    }

    public function scopeCreatedBefore($query, $date)
    {
        return $query->whereDate('created_at', '<', $date);
    }

    public function scopeCreatedAtDate($query, $date)
    {
        return $query->whereDate('created_at', $date);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    public function scopeWhereDateBetween($query, $fieldName, $fromDate, $toDate, $dates_format)
    {
        $from = is_null($fromDate) ? now()->subYears(10)->format($dates_format) : $fromDate;
        $to = is_null($toDate) ? now()->addYears(10)->format($dates_format) : $toDate;

        $from = Carbon::createFromFormat($dates_format, $from)->format("Y-m-d");
        $to = Carbon::createFromFormat($dates_format, $to)->format("Y-m-d");

        return $query->whereDate($fieldName, '>=', $from)
            ->whereDate($fieldName, '<=', $to);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
}
