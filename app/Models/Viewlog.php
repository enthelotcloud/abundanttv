<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Viewlog extends Model
{
    use HasFactory;

    protected $table = 'view_logs'; // if you renamed the migration

    protected $fillable = [
        'show_id', 'schedule_id', 'ip_address', 'user_agent',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
