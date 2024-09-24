<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_test extends Model
{
    use HasFactory;
    public $timestamps = false;

        protected $fillable = [
        'test_id',
        'user_id',
        'the_part_to_test_in',
        'rating',
        'notes'
            ];
}
