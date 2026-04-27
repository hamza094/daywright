<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __invoke(Request $request): View
    {
        return view('home', [
            'shouldBootstrapSession' => (bool) $request->route('shouldBootstrapSession', true),
        ]);
    }
}
