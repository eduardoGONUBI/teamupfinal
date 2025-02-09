<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainEvent extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'event_type',
        'payload',
        'aggregate_id',
    ];

    // Optionally, you can cast payload to an array automatically
    protected $casts = [
        'payload' => 'array',
    ];
}
