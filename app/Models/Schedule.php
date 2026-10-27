<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'show_id', 'start_time', 'end_time', 'label', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Schedule $s) {
            // auto-fill end_time from the show's duration_minutes
            if ($s->start_time && !$s->end_time) {
                $show = $s->show ?? Show::find($s->show_id);
                if ($show) {
                    $start = Carbon::parse($s->start_time);
                    $s->end_time = $start->copy()
                        ->addMinutes($show->duration_minutes)
                        ->format('H:i:s');
                }
            }
        });
    }

    // ---- Each schedule belongs to exactly one show ----
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /*
    |-------------------------------------------------------------
    | RELATIONSHIP: a schedule has MANY presenters.
    | Same pivot table 'schedule_presenter', just from the other side.
    | NOTE the flipped columns: schedule_id first, presenter_id second.
    |-------------------------------------------------------------
    */
    public function presenters(): BelongsToMany
    {
        return $this->belongsToMany(
                Presenter::class,      // other side
                'schedule_presenter',  // pivot (link) table
                'schedule_id',         // this model's column in pivot
                'presenter_id'         // other model's column in pivot
            )
            ->withPivot('role', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    // ---- Query scopes used by the Livewire pages ----

    public function scopeOnDay($query, string $day)
    {
        return $query->whereHas('show', fn ($q) => $q->airingOn($day));
    }

    public function scopeToday($query)
    {
        return $query->onDay(now()->englishDayOfWeek);
    }

    public function scopeAtTime($query, string $time)
    {
        return $query->where('start_time', '<=', $time)
                     ->where('end_time',   '>=', $time);
    }

    public function scopeLiveNow($query)
    {
        return $query->today()->atTime(now()->format('H:i:s'));
    }

    public function scopeUpNext($query)
    {
        return $query->today()
                     ->where('start_time', '>', now()->format('H:i:s'))
                     ->orderBy('start_time');
    }

    public function scopeForPresenter($query, int $presenterId)
    {
        return $query->whereHas('presenters',
            fn ($q) => $q->where('presenters.id', $presenterId));
    }

    // ---- Helpers ----

    public function getIsLiveNowAttribute(): bool
    {
        return $this->show->airsToday()
            && $this->start_time <= now()->format('H:i:s')
            && $this->end_time   >= now()->format('H:i:s');
    }

    public function getTimeLabelAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A')
             . ' – ' .
               Carbon::parse($this->end_time)->format('g:i A');
    }
}
