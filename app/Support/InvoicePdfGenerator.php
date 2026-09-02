<?php

namespace App\Support;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfGenerator
{
    public static function generate(Invoice $invoice): string
    {
        $invoice->loadMissing('lineItems');

        $pdf = Pdf::loadView('pdfs.invoice', [
            'invoice' => $invoice,
            'logoDataUri' => static::logoDataUri($invoice),
        ]);

        $path = sprintf('invoices/%d/%s.pdf', $invoice->year, $invoice->invoice_number);
        Storage::disk('local')->put($path, $pdf->output());

        $invoice->forceFill([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->saveQuietly();

        return $path;
    }

    protected static function logoDataUri(Invoice $invoice): ?string
    {
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
