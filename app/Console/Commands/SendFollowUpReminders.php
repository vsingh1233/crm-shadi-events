<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Notifications\CrmNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendFollowUpReminders extends Command
{
    protected $signature = 'crm:send-reminders';

    protected $description = 'Queue due follow-up reminders once for each pending follow-up';

    public function handle(): int
    {
        $count = 0;
        FollowUp::where('status', 'pending')->where('due_at', '<=', now())->whereNull('reminder_queued_at')->select('id')->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                DB::transaction(function () use ($row, &$count) {
                    $followUp = FollowUp::with('lead', 'responsible')->lockForUpdate()->find($row->id);
                    if (! $followUp || $followUp->status !== 'pending' || $followUp->reminder_queued_at || ! $followUp->lead || $followUp->lead->isClosed() || ! $followUp->responsible->can('view', $followUp->lead)) {
                        return;
                    }
                    $followUp->responsible->notify(new CrmNotification('Follow-up due', 'Your follow-up scheduled for '.$followUp->due_at->copy()->timezone(config('crm.timezone'))->format('d M Y H:i').' (Asia/Kolkata) is due.', $followUp->lead_id, $followUp->id, $followUp->due_at->format('Y-m-d H:i:s')));
                    $followUp->reminder_queued_at = now();
                    $followUp->save();
                    $count++;
                });
            }
        });
        $this->info("Queued {$count} follow-up reminders.");

        return self::SUCCESS;
    }
}
