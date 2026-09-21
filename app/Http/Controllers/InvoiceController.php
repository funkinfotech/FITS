<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $invoices = $user->company_id
            ? Invoice::where('company_id', $user->company_id)->latest('issue_date')->paginate(15)
            : Invoice::whereRaw('1 = 0')->paginate(15);

        return view('invoices.index', [
            'invoices' => $invoices,
            'balanceOwed' => $user->company?->balance_owed ?? '0.00',
        ]);
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load('lineItems');
        $receipt = $invoice->payments()->active()->first();

        return view('invoices.show', compact('invoice', 'receipt'));
    }

    public function downloadPdf(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        abort_unless($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404);

        return Storage::disk('local')->download($invoice->pdf_path, "{$invoice->invoice_number}.pdf");
    }

    public function downloadReceipt(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $receipt = $invoice->payments()->active()->first();

        abort_unless($receipt && $receipt->pdf_path && Storage::disk('local')->exists($receipt->pdf_path), 404);

        return Storage::disk('local')->download($receipt->pdf_path, "{$receipt->receipt_number}.pdf");
    }
}
