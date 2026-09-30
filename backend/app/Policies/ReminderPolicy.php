<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    public function __construct(
        private readonly InquiryPolicy $inquiries
    ) {
    }

    public function view(User $user, Reminder $reminder): bool
    {
        $reminder->loadMissing('inquiry');

        return $reminder->inquiry !== null
            && $this->inquiries->view($user, $reminder->inquiry);
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $this->view($user, $reminder)
            && (
                $user->canManageInquiries()
                || (int) $reminder->user_id === $user->id
            );
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->update($user, $reminder);
    }
}
