<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;

class InquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && ($user->canManageInquiries() || $user->isAgent());
    }

    public function view(User $user, Inquiry $inquiry): bool
    {
        if (! $user->is_active || $inquiry->trashed()) {
            return false;
        }

        return $user->canManageInquiries()
            || ($user->isAgent() && $inquiry->assigned_to === $user->id);
    }

    public function update(User $user, Inquiry $inquiry): bool
    {
        return $this->view($user, $inquiry);
    }

    public function assign(User $user, Inquiry $inquiry): bool
    {
        return $user->is_active
            && $user->canManageInquiries()
            && ! $inquiry->trashed();
    }

    public function delete(User $user, Inquiry $inquiry): bool
    {
        return $user->is_active
            && $user->isAdmin()
            && ! $inquiry->trashed();
    }

    public function export(User $user): bool
    {
        return $user->is_active && $user->canManageInquiries();
    }
}
