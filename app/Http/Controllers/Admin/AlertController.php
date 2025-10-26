<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlertController extends Controller
{
    public function index()
    {
        $alerts = Alert::latest()->get();
        
        return Inertia::render('Admin/Alerts/Index', [
            'alerts' => $alerts
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Alerts/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'message' => 'required|string',
            'is_active' => 'boolean'
        ]);

        Alert::create($request->all());

        return redirect()->route('admin.alerts.index')
            ->with('success', 'Alert created successfully.');
    }

    public function edit(Alert $alert)
    {
        return Inertia::render('Admin/Alerts/Edit', [
            'alert' => $alert
        ]);
    }

    public function update(Request $request, Alert $alert)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'message' => 'required|string',
            'is_active' => 'boolean'
        ]);

        $alert->update($request->all());

        return redirect()->route('admin.alerts.index')
            ->with('success', 'Alert updated successfully.');
    }

    public function destroy(Alert $alert)
    {
        $alert->delete();

        return redirect()->route('admin.alerts.index')
            ->with('success', 'Alert deleted successfully.');
    }
}