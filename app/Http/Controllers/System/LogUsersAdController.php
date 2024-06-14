<?php

namespace App\Http\Controllers\System;

use App\Models\LogUsersAd;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LogUsersAdController extends Controller
{
    public function getAll()
    {
        $items = LogUsersAd::all();

        $data = ['data' => $items];

        return response()->json($data);
    }
}
