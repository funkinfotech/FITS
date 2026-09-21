<?php

namespace App\Support;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PaymentReceiptPdfGenerator
{
    public static function generate(Payment $payment): string
    {
        $payment->loadMissing('invoice.lineItems');

        $pdf = Pdf::loadView('pdfs.payment-receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'logoDataUri' => static::logoDataUri($payment),
        ]);

        $path = sprintf('payments/%d/%s.pdf', $payment->year, $payment->receipt_number);
        Storage::disk('local')->put($path, $pdf->output());

        $payment->forceFill([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->saveQuietly();

        return $path;
    }

    protected static function logoDataUri(Payment $payment): ?string
    {
        $invoice = $payment->invoice;

        if ($invoice->from_logo_path && Storage::disk('public')->exists($invoice->from_logo_path)) {
            $contents = Storage::disk('public')->get($invoice->from_logo_path);
            $mime = Storage::disk('public')->mimeType($invoice->from_logo_path) ?: 'image/png';

            return "data:{$mime};base64," . base64_encode($contents);
        }

        $fallback = public_path('images/funkit-logo.png');

        if (is_file($fallback)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($fallback));
        }

        return null;
    }
}
