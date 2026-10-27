<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bannedword extends Model
{
    use HasFactory;

    protected $table    = 'banned_words';
    protected $fillable = ['word', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public static function matches(?string $text): bool
    {
        if (!$text) return false;
        foreach (static::where('is_active', true)->pluck('word') as $w) {
            if ($w !== '' && stripos($text, $w) !== false) return true;
        }
        return false;
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
