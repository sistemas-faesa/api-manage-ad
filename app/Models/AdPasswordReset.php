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
        'last_used_at',
        'created_at',
    ];
}
