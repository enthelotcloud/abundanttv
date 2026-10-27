<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Show extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'category', 'thumbnail',
        'stream_url', 'duration_minutes', 'airing_days', 'is_active',
    ];

    protected $casts = [
        'airing_days'      => 'array',
        'duration_minutes' => 'integer',
        'is_active'        => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Show $show) {
            $show->slug ??= Str::slug($show->title);
        });
    }

    // ---- Relations ----

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // ---- Days helpers ----

    public function airsOn(string $day): bool
    {
        return in_array(strtolower($day), array_map('strtolower', $this->airing_days ?? []), true);
    }

    public function airsToday(): bool
    {
        return $this->airsOn(now()->englishDayOfWeek);
    }

    public function scopeAiringOn($query, string $day)
    {
        return $query->whereJsonContains('airing_days', strtolower($day));
    }

    public function scopeAiringToday($query)
    {
        return $query->airingOn(now()->englishDayOfWeek);
    }

    // ---- Derived attributes ----

    public function getDurationLabelAttribute(): string
    {
        $h = intdiv($this->duration_minutes, 60);
        $m = $this->duration_minutes % 60;
        return trim(($h ? "{$h}h " : '') . ($m ? "{$m}m" : '')) ?: '0m';
    }

    /** Unique presenters across all schedules of this show. */
    public function allPresenters(): Collection
    {
        return $this->schedules()
            ->with('presenters')
            ->get()
            ->flatMap->presenters
            ->unique('id')
            ->values();
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
