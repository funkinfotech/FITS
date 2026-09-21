<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

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
}
