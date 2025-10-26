@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Alert System Test</h1>
    
    @if($activeAlert)
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <strong>✅ Alert System Working!</strong><br>
            <strong>Title:</strong> {{ $activeAlert->title ?? 'No Title' }}<br>
            <strong>Message:</strong> {{ $activeAlert->message }}<br>
            <strong>Status:</strong> {{ $activeAlert->is_active ? 'Active' : 'Inactive' }}
        </div>
    @else
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            <strong>⚠️ No Active Alert</strong><br>
            No active alerts are currently set. Create one in the admin panel to test the system.
        </div>
    @endif
    
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Quick Alert System Features</h2>
        <ul class="list-disc list-inside space-y-2">
            <li>✅ Alert Model and Migration created</li>
            <li>✅ Admin Controller with full CRUD operations</li>
            <li>✅ Routes configured with admin middleware</li>
            <li>✅ React components for admin interface</li>
            <li>✅ Global view composer for alert sharing</li>
            <li>✅ Alert display in main layout</li>
            <li>✅ Navigation link in admin panel</li>
        </ul>
        
        <div class="mt-6">
            <a href="/admin/alerts" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Manage Alerts (Admin Only)
            </a>
        </div>
    </div>
</div>
@endsection