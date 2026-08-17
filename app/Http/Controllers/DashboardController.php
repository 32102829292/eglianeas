<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return redirect()->to(Auth::user()->getDashboardRoute());
    }
}
