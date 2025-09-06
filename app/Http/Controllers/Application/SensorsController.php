<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SensorsController extends Controller
{
        public function index()
    {
        return view('application.sensors.index');
    }
}
