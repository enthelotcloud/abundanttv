<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Presenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'role',
        'phone', 'phone_visible',
        'bio', 'photo',
        'email', 'email_visible',
        'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'phone_visible'  => 'boolean',
        'email_visible'  => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Presenter $p) {
            $p->slug ??= Str::slug($p->name);
        });
    }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(
                Schedule::class,
                'schedule_presenter',          // explicit table name
                'presenter_id',                // FK on pivot to this model
                'schedule_id'                  // FK on pivot to related model
            )
            ->withPivot('role', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    // ---- Helpers ----

    /** Unique shows across all slots this presenter is on. */
    public function shows()
    {
        return Show::whereIn(
            'id',
            $this->schedules()->pluck('show_id')->unique()
        )->get();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
