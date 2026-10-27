<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'label', 'type', 'value', 'icon_path', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Auto-builds the clickable URL from the type + value.
     * whatsapp: +254712345678 → https://wa.me/254712345678
     * phone:    +254712345678 → tel:+254712345678
     * email:    hi@site.com   → mailto:hi@site.com
     * other:    passed through as-is
     */
    public function getHrefAttribute(): string
    {
        return match ($this->type) {
            'phone'    => 'tel:' . preg_replace('/\s+/', '', $this->value),
            'whatsapp' => 'https://wa.me/' . preg_replace('/\D+/', '', $this->value),
            'email'    => 'mailto:' . $this->value,
            default    => $this->value,
        };
    }

    /** Public URL for the uploaded icon. */
    public function getIconUrlAttribute(): string
    {
        return asset('storage/' . $this->icon_path);
    }
}
