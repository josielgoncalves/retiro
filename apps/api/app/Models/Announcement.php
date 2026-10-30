<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['retreat_id', 'title', 'content', 'published_at', 'visible_until', 'created_by'])]
class Announcement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'visible_until' => 'datetime',
        ];
    }

    public function retreat(): BelongsTo
    {
        return $this->belongsTo(Retreat::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        if ($this->published_at === null || $this->published_at->isFuture()) {
            return false;
        }

        if ($this->visible_until !== null && $this->visible_until->isPast()) {
            return false;
        }

        return true;
    }

    public function scopeVisible($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('visible_until')->orWhere('visible_until', '>=', now());
            });
    }
}
