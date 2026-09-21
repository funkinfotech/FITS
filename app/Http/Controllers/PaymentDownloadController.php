<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

class PaymentDownloadController extends Controller
{
    public function show(Payment $payment)
    {
        abort_unless(
            $payment->pdf_path && Storage::disk('local')->exists($payment->pdf_path),
            404
        );

        return Storage::disk('local')->download($payment->pdf_path, "{$payment->receipt_number}.pdf");
    }
}
