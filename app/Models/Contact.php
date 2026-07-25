<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'phone',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function emails()
    {
        return $this->hasMany(ContactEmail::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function getEmailAttribute(): ?string
    {
        return $this->emails->firstWhere('is_primary', true)?->email
            ?? $this->emails->first()?->email;
    }
}
