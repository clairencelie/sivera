<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RabItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'category',
        'item_name',
        'specification',
        'volume',
        'unit',
        'proposed_price',
    ];

    protected $casts = [
        'volume' => 'decimal:2',
        'proposed_price' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function validationResult(): HasOne
    {
        return $this->hasOne(ValidationResult::class);
    }

    public function getTotalProposedPriceAttribute(): float
    {
        return (float) $this->volume * (float) $this->proposed_price;
    }
}
