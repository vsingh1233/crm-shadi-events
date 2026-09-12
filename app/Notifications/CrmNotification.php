<?php

namespace App\Notifications;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CrmNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $title, public string $message, public ?int $leadId = null, public ?int $followUpId = null, public ?string $dueAt = null, public bool $adminOnly = false)
    {
        $this->onConnection('database');
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function via(object $notifiable): array
    {
        return config('crm.email_enabled') ? ['database', 'mail'] : ['database'];
    }

    public function viaConnections(): array
    {
        return ['database' => 'sync', 'mail' => 'database'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($channel === 'mail' && ! config('crm.email_enabled')) {
            return false;
        }
        $user = User::find($notifiable->id);
        if (! $user || ! $user->is_active || ($this->adminOnly && ! $user->isAdministrator())) {
            return false;
        }
        if ($this->leadId) {
            $lead = Lead::find($this->leadId);
            if (! $lead || ! $user->can('view', $lead)) {
                return false;
            }
        }
        if ($this->followUpId) {
            $followUp = FollowUp::find($this->followUpId);
            if (! $followUp || $followUp->status !== 'pending' || $followUp->responsible_id !== $user->id || $followUp->due_at->format('Y-m-d H:i:s') !== $this->dueAt || $lead->isClosed()) {
                return false;
            }
        }

        return true;
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'lead_id' => $this->leadId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->greeting('Hello '.$notifiable->name)->line($this->message)->action('Open ShadiEvents CRM', $this->leadId ? route('leads.show', $this->leadId) : route('dashboard'));
    }
}
