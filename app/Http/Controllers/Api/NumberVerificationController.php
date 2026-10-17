<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VerifiedNumber;
use App\Services\PreviousOrderRestrictionService;
use Illuminate\Http\Request;

class NumberVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'beneficiary_number' => ['required', 'string', 'regex:/^\\d{10}$/'],
            'network' => ['required', 'string', 'max:30'],
        ]);

        $number = $validated['beneficiary_number'];
        $network = $validated['network'];
        $isMtn = strtolower($network) === 'mtn';

        $checks = [
            'verified_number' => !$isMtn || VerifiedNumber::isAllowed($number),
            'previous_order' => !$isMtn || PreviousOrderRestrictionService::isAllowed($number, $network),
        ];

        return response()->json([
            'allowed' => !in_array(false, $checks, true),
            'beneficiary_number' => $number,
            'network' => $network,
            'checks' => $checks,
        ]);
    }
}
