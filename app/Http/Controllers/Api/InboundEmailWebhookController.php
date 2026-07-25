<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundEmailJob;
use Illuminate\Http\Request;

class InboundEmailWebhookController extends Controller
{
    public function store(Request $request)
    {
        $secret = $request->header('X-Brevo-Webhook-Secret');

        if (! $secret || ! hash_equals((string) config('services.brevo.inbound_webhook_secret'), $secret)) {
            abort(403);
        }

        foreach ($request->input('items', []) as $item) {
            ProcessInboundEmailJob::dispatch($item);
        }

        return response()->json(['status' => 'ok']);
    }
}
