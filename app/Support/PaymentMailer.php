<?php

namespace App\Support;

use App\Mail\PaymentReceiptMail;
use App\Mail\PaymentReceivedAdminNotification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class PaymentMailer
{
    public static function sendReceipt(Payment $payment, iterable $contacts): void
    {
        if (! $payment->pdf_path) {
            PaymentReceiptPdfGenerator::generate($payment);
        }

        $sent = false;

        foreach ($contacts as $contact) {
            if (! $contact->email) {
                continue;
            }

            Mail::to($contact->email)->queue(new PaymentReceiptMail($payment));
            $sent = true;
        }

        if ($sent) {
            $payment->forceFill(['emailed_at' => now()])->saveQuietly();
        }
    }

    public static function notifyAdmins(Payment $payment): void
    {
        if (! $payment->pdf_path) {
            PaymentReceiptPdfGenerator::generate($payment);
        }

        $admins = User::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(new PaymentReceivedAdminNotification($payment));
        }

        if ($admins->isNotEmpty()) {
            $payment->forceFill(['admin_notified_at' => now()])->saveQuietly();
        }
    }
}
