<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

class InvoiceDownloadController extends Controller
{
    public function show(Invoice $invoice)
    {
        abort_unless(
            $invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path),
            404
        );

        return Storage::disk('local')->download($invoice->pdf_path, "{$invoice->invoice_number}.pdf");
    }
}
