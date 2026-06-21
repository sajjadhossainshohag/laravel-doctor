<?php

namespace SajjadHossain\Doctor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('doctor::dashboard.index');
    }

    public function history()
    {
        return view('doctor::dashboard.index');
    }

    public function triggerScan(Request $request)
    {
        return redirect()->route('doctor.dashboard')
            ->with('status', 'Scan triggered (queued).');
    }
}
