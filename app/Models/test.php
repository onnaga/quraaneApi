<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class test extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'at',
        'aukaf',
        'notes',
    ];
}
