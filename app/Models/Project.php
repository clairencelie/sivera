<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location_city',
        'location_province',
        'total_proposed_budget',
    ];

    protected $casts = [
        'total_proposed_budget' => 'decimal:2',
    ];

    public function rabItems(): HasMany
    {
        return $this->hasMany(RabItem::class);
    }

    public function preparationItems(): HasMany
    {
        return $this->rabItems()->where('category', 'Persiapan & Akhir');
    }

    public function mainWorkItems(): HasMany
    {
        return $this->rabItems()->where('category', 'Pekerjaan Utama');
    }
}
