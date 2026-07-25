<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InboundEmailWebhookController;

Route::post('/webhooks/brevo/inbound', [InboundEmailWebhookController::class, 'store'])
    ->name('webhooks.brevo.inbound');
