<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id', 'show_id', 'name', 'phone', 'device_id',
        'body', 'ip_address', 'has_banned_word',
    ];

    protected $casts = ['has_banned_word' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (Comment $c) {
            $c->has_banned_word = Bannedword::matches($c->body);
        });
    }

    public function show(): BelongsTo     { return $this->belongsTo(Show::class); }
    public function schedule(): BelongsTo { return $this->belongsTo(Schedule::class); }

    public function scopeClean($q)  { return $q->where('has_banned_word', false); }
    public function scopeRecent($q) { return $q->latest(); }
}
