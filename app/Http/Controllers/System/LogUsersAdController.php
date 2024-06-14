<?php

namespace App\Http\Controllers\System;

use App\Models\LogUsersAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

class LogUsersAdController extends Controller
{
    public function getAll()
    {
        $query = LogUsersAd::query()->where('id', '>', 0);

        $qs = request()->all();

        if (isset($qs['search']) && !empty($qs['search'])) {
            $query
                ->where(
                    function (Builder $query) use ($qs) {
                        $query
                            ->orWhere('nome', 'like',  '%' . $qs['search'] . '%')
                            ->orWhere('cpf', '=',  $qs['search'])
                            ->orWhere('matricula', '=',  $qs['search'])
                            ->orWhere('login', 'like',  '%' . $qs['search'] . '%')
                            ->orWhere('evento', '=',  $qs['search'])
                            ->orWhere('status', '=',  $qs['search']);
                    }
                );
        }

        $items = $query->orderBy('created_at', 'desc')->paginate(30);

        return response()->json($items);
    }
}
