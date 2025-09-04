<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AFAController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard/AFARegistration');
    }

    public function afaOrders()
    {
        $afaOrders = auth()->user()->afaOrders()->with('afaproduct')->latest()->get();
        return Inertia::render('Dashboard/AFAOrders', [
            'afaOrders' => $afaOrders
        ]);
    }
}