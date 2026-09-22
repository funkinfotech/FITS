<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

// This policy is auto-discovered globally by Laravel, not scoped to the portal — the
// admin Filament panel has no custom canView()/canEdit() overrides on InvoiceResource,
// so it silently defers to this class for authorization too. Any `is_admin` bypass added
// here must stay unconditional: omitting it previously locked every admin out of every
// invoice in production.
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->is_admin
            || ($user->company_id !== null
                && $invoice->company_id === $user->company_id
                && $invoice->status !== InvoiceStatus::Draft);
    }
}
