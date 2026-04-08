<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(Request $request): Response
    {
        $referralCode = $request->query('ref');
        $referrer = null;
        
        if ($referralCode) {
            $referrer = User::where('referral_code', $referralCode)->first();
        }
        
        return Inertia::render('auth/register', [
            'referrer' => $referrer,
            'referralCode' => $referralCode
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'referral_code' => 'nullable|string|exists:users,referral_code'
        ]);

        $user = User::create([
            'name' => $request->name,
            'business_name' => $request->business_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Create referral record if referral_code is provided
        $referralMessage = null;
        if ($request->referral_code) {
            $referrer = User::where('referral_code', $request->referral_code)->first();
            if ($referrer) {
                \App\Models\Referral::create([
                    'referrer_id' => $referrer->id,
                    'referred_id' => $user->id,
                    'referred_at' => now()
                ]);
                $referralMessage = "You were successfully referred by {$referrer->name}. You can now upgrade to agent status to start earning commissions!";
            }
        }

        event(new Registered($user));

        Auth::login($user);

        // Redirect with appropriate success message
        if ($referralMessage) {
            return to_route('dashboard')->with('success', $referralMessage)->with('show_upgrade_prompt', true);
        }
        
        return to_route('dashboard')->with('success', 'Welcome! Your account has been created successfully.');
    }
}
