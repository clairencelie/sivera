<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'rab_item_id',
        'ai_model_used',
        'found_item_name',
        'is_equivalent',
        'price_min',
        'price_max',
        'reference_url',
        'source_urls',
        'web_search_queries',
        'grounding_metadata',
        'latency_ms',
        'api_error',
        'status',
        'reasoning',
    ];

    protected $casts = [
        'is_equivalent' => 'boolean',
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
        'source_urls' => 'array',
        'web_search_queries' => 'array',
        'grounding_metadata' => 'array',
        'latency_ms' => 'integer',
    ];

    public function rabItem(): BelongsTo
    {
        return $this->belongsTo(RabItem::class);
    }

    public function getPriceRangeStringAttribute(): string
    {
        if ($this->price_min && $this->price_max) {
            return 'Rp ' . number_format($this->price_min, 0, ',', '.') . ' – Rp ' . number_format($this->price_max, 0, ',', '.');
        }
        return 'Tidak tersedia';
    }
}
