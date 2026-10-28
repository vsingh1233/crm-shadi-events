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

    public function __construct(public string $title, public string $message, public ?int $leadId = null, public ?int $followUpId = null, public ?string $dueAt = null, public bool $adminOnly = false, public ?string $reminderQueuedAt = null)
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
            if (($this->reminderQueuedAt ?? null) !== null
                && $followUp->reminder_queued_at?->format('Y-m-d H:i:s') !== $this->reminderQueuedAt) {
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
        if ($this->followUpId) {
            $followUp = FollowUp::with(['lead.owner', 'lead.creator', 'responsible'])->findOrFail($this->followUpId);
            $lead = $followUp->lead;
            $timezone = config('crm.timezone');
            $overdue = $followUp->due_at->lt(now()->subMinute());
            $heading = $overdue ? 'Your follow-up is overdue' : 'Your follow-up is due';
            $details = [
                'Client' => $lead->name,
                'Couple name' => $lead->couple_name,
                'Phone' => $lead->phone,
                'Email' => $lead->email,
                'Client location' => $lead->client_location,
                'Wedding location' => $lead->wedding_location,
                'Wedding start' => $lead->wedding_start_date?->format('d M Y'),
                'Wedding end' => $lead->wedding_end_date?->format('d M Y'),
                'Stage' => Lead::STATUSES[$lead->status] ?? $lead->status,
                'Lead quality' => Lead::TEMPERATURES[$lead->temperature] ?? 'Unassessed',
                'Owner' => $lead->owner?->name,
                'Created by' => $lead->creator?->name,
                'Source' => ucfirst($lead->source ?? ''),
                'Created (IST)' => $lead->created_at?->copy()->timezone($timezone)->format('d M Y, H:i'),
                'Updated (IST)' => $lead->updated_at?->copy()->timezone($timezone)->format('d M Y, H:i'),
            ];

            return (new MailMessage)
                ->subject('Follow-up '.($overdue ? 'overdue' : 'due').': '.($lead->couple_name ? $lead->couple_name.' · ' : '').$lead->name)
                ->view(['html' => 'emails.follow-up-reminder', 'text' => 'emails.follow-up-reminder-text'], [
                    'heading' => $heading,
                    'overdue' => $overdue,
                    'recipientName' => $followUp->responsible?->name ?? $notifiable->name,
                    'clientName' => $lead->name,
                    'coupleName' => $lead->couple_name,
                    'dueAt' => $followUp->due_at->copy()->timezone($timezone)->format('d M Y, H:i').' IST',
                    'notes' => $followUp->notes,
                    'details' => $details,
                    'leadUrl' => route('leads.show', $lead),
                    'phoneUrl' => $lead->phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $lead->phone) : null,
                    'emailUrl' => $lead->email ? 'mailto:'.$lead->email : null,
                    'logoUrl' => asset('logo.webp'),
                ]);
        }

        return (new MailMessage)->subject($this->title)->greeting('Hello '.$notifiable->name)->line($this->message)->action('Open ShadiEvents CRM', $this->leadId ? route('leads.show', $this->leadId) : route('dashboard'));
    }
}
