<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    protected $fillable = [
        'logo_path',
        'business_name',
        'address',
        'email',
        'phone',
        'tax_id',
        'bank_details',
        'default_net_days',
        'default_terms_text',
    ];

    protected $casts = [
        'default_net_days' => 'integer',
    ];

    public static function current(): self
    {
        return once(fn () => static::query()->first() ?? static::create([
            'business_name' => 'FunkIT HelpDesk',
            'default_net_days' => 30,
        ]));
    }
}
