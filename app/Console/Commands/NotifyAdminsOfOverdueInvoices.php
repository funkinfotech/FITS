<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceOverdueAdminNotification;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotifyAdminsOfOverdueInvoices extends Command
{
    protected $signature = 'invoices:notify-admins-overdue';

    protected $description = 'Email admin users about overdue invoices awaiting a decision (send reminder or ignore)';

    protected const NOTIFY_INTERVAL_DAYS = 3;

    public function handle(): int
    {
        $admins = User::where('is_admin', true)->get();

        if ($admins->isEmpty()) {
            $this->info('No admin users to notify.');

            return self::SUCCESS;
        }

        $notified = 0;
        $cutoff = now()->subDays(self::NOTIFY_INTERVAL_DAYS);

        Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->whereNull('overdue_ignored_at')
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('overdue_admin_notified_at')
                    ->orWhere('overdue_admin_notified_at', '<', $cutoff);
            })
            ->chunkById(50, function ($invoices) use ($admins, &$notified) {
                foreach ($invoices as $invoice) {
                    foreach ($admins as $admin) {
                        Mail::to($admin->email)->queue(new InvoiceOverdueAdminNotification($invoice));
                    }

                    $invoice->forceFill(['overdue_admin_notified_at' => now()])->saveQuietly();
                    $notified++;
                }
            });

        $this->info("Notified admins about {$notified} overdue invoice(s).");

        return self::SUCCESS;
    }
}
