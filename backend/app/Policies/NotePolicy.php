<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    public function __construct(
        private readonly InquiryPolicy $inquiries
    ) {
    }

    public function view(User $user, Note $note): bool
    {
        $note->loadMissing('inquiry');

        return $note->inquiry !== null
            && $this->inquiries->view($user, $note->inquiry);
    }

    public function update(User $user, Note $note): bool
    {
        return $this->view($user, $note)
            && ($user->isAdmin() || (int) $note->user_id === $user->id);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->update($user, $note);
    }
}
