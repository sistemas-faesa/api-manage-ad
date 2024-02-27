<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LyDocente extends Model
{
    use HasFactory;

	protected $table = 'LY_DOCENTE';

	protected $connection = 'sqlsrv_lyceum';
}
