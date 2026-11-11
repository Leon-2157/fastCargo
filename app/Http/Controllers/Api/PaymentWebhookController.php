<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentWebhookController extends Controller
{
    // TODO: implementasi Phase 1
    public function handle(Request $request)
    {
        return response()->json(['status' => 'ok']);
    }
}
