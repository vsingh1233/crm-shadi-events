<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function view(User $user, Lead $lead): bool
    {
        return $user->is_active && ($user->isAdministrator() || $lead->owner_id === $user->id || $lead->collaborators()->where('users.id', $user->id)->exists());
    }

    public function update(User $user, Lead $lead): bool
    {
        return ! $lead->trashed() && $this->view($user, $lead);
    }

    public function manageTeam(User $user, Lead $lead): bool
    {
        return $user->is_active && ! $lead->trashed() && ($user->isAdministrator() || $lead->owner_id === $user->id);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->is_active && $user->isAdministrator();
    }

    public function restore(User $user, Lead $lead): bool
    {
        return $this->delete($user, $lead);
    }
}
