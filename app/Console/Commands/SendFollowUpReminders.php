<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Notifications\CrmNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SendFollowUpReminders extends Command
{
    protected $signature = 'crm:send-reminders';

    protected $description = 'Queue due follow-up reminders and repeat daily at 10 AM while pending';

    public function handle(): int
    {
        $count = 0;
        $currentTime = now();
        $localTime = $currentTime->copy()->timezone(config('crm.timezone'));
        $repeatTimeReached = $localTime->hour >= 10;
        $startOfDay = $localTime->copy()->startOfDay()->utc();

        FollowUp::where('status', 'pending')
            ->where('due_at', '<=', $currentTime)
            ->where(function (Builder $query) use ($repeatTimeReached, $startOfDay): void {
                $query->whereNull('reminder_queued_at');
                if ($repeatTimeReached) {
                    $query->orWhere('reminder_queued_at', '<', $startOfDay);
                }
            })
            ->select('id')
            ->chunkById(100, function (Collection $rows) use (&$count, $currentTime, $repeatTimeReached, $startOfDay): void {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$count, $currentTime, $repeatTimeReached, $startOfDay): void {
                        $followUp = FollowUp::with('lead', 'responsible')->lockForUpdate()->find($row->id);
                        if (! $followUp || $followUp->status !== 'pending' || $followUp->due_at->gt($currentTime)
                            || ! $followUp->lead || $followUp->lead->isClosed()
                            || ! $followUp->responsible || ! $followUp->responsible->can('view', $followUp->lead)) {
                            return;
                        }
                        if ($followUp->reminder_queued_at
                            && (! $repeatTimeReached || $followUp->reminder_queued_at->gte($startOfDay))) {
                            return;
                        }

                        $followUp->reminder_queued_at = $currentTime;
                        $followUp->save();

                        $lead = $followUp->lead;
                        $message = 'Client: '.$lead->name.'.';
                        if ($lead->couple_name) {
                            $message .= ' Couple: '.$lead->couple_name.'.';
                        }
                        $message .= ' Your follow-up scheduled for '
                            .$followUp->due_at->copy()->timezone(config('crm.timezone'))->format('d M Y H:i')
                            .' ('.config('crm.timezone').') is due. Purpose: '.$followUp->notes;

                        $followUp->responsible->notify(new CrmNotification(
                            'Follow-up due',
                            $message,
                            $followUp->lead_id,
                            $followUp->id,
                            $followUp->due_at->format('Y-m-d H:i:s'),
                            reminderQueuedAt: $currentTime->format('Y-m-d H:i:s'),
                        ));
                        $count++;
                    });
                }
            });

        $this->info("Queued {$count} follow-up reminders.");

        return self::SUCCESS;
    }
}
