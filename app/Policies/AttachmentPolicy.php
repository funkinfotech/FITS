<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        $ticket = $attachment->ticket();

        if ($ticket === null) {
            return false;
        }

        $parent = $attachment->attachable;

        // Attachments on internal notes are staff-only.
        if ($parent instanceof Comment && $parent->is_internal && ! $user->is_admin) {
            return false;
        }

        return $user->can('view', $ticket);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // A customer may remove an attachment they uploaded themselves.
        return $attachment->uploaded_by_user_id === $user->id
            && $this->view($user, $attachment);
    }
}
