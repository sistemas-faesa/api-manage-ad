<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LyPessoa extends Model
{
    use HasFactory;

	protected $table = 'LY_PESSOA';

	protected $connection = 'sqlsrv_lyceum';

	public $timestamps = false;

	public $incrementing = false;

	protected $primaryKey = 'CPF';
}
