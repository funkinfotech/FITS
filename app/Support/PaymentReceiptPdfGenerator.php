<?php

namespace App\Support;

use App\Models\BusinessProfile;
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
            'logoDataUri' => static::logoDataUri(),
        ]);

        $path = sprintf('payments/%d/%s.pdf', $payment->year, $payment->receipt_number);
        Storage::disk('local')->put($path, $pdf->output());

        $payment->forceFill([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->saveQuietly();

        return $path;
    }

    /**
     * A receipt is generated fresh at the moment of payment, so it uses the
     * business's current logo — not the invoice's frozen from_logo_path
     * snapshot (which intentionally stays fixed at whatever it was when the
     * invoice was issued, so old invoices don't silently change if the
     * business rebrands later).
     */
    public static function logoDataUri(): ?string
    {
        $logoPath = BusinessProfile::current()->logo_path;

        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $contents = Storage::disk('public')->get($logoPath);
            $mime = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';

            return "data:{$mime};base64," . base64_encode($contents);
        }

        $fallback = public_path('images/funkit-logo.png');

        if (is_file($fallback)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($fallback));
        }

        return null;
    }
}
