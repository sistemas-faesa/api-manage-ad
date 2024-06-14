<?php

namespace App\Http\Controllers\System;

use App\Models\AdPasswordReset;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

class AdPasswordResetController extends Controller
{
    public function getAll()
    {
        $query = AdPasswordReset::query()->where('id', '>', 0);

        $qs = request()->all();

        if (isset($qs['search']) && !empty($qs['search'])) {
            $query
                ->where(
                    function (Builder $query) use ($qs) {
                        $query
                            ->orWhere('email', 'like',  '%' . $qs['search'] . '%')
                            ->orWhere('cpf', 'like',  '%' . $qs['search'] . '%');
                    }
                );
        }

        $items = $query->orderBy('created_at', 'desc')->paginate(30);

        return response()->json($items);
    }
}
