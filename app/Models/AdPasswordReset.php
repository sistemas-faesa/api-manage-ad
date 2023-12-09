<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdPasswordReset extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = [
        'email',
        'cpf',
        'token',
        'updated_at',
        'created_at',
    ];

    protected $hidden = [
        'token'
    ];
}
