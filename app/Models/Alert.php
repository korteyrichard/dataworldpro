<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'title',
        'message',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
