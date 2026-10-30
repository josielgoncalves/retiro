<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['partner_one_name', 'partner_two_name', 'phone', 'secondary_phone', 'email', 'notes'])]
class Couple extends Model
{
    use HasFactory;

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
