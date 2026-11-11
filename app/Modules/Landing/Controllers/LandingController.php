<?php

namespace App\Modules\Landing\Controllers;

use Illuminate\Routing\Controller;

class LandingController extends Controller
{
    public function index()
    {
        return view('landing.index');
    }
}
