<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogUsersAd extends Model
{
	use HasFactory;

	protected $fillable = [
		'nome',
		'cpf',
		'matricula',
		'login',
		'evento',
		'obs',
		'status'
	];
}
