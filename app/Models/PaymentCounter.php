<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCounter extends Model
{
    protected $fillable = [
        'year',
        'last_sequence',
    ];
}
