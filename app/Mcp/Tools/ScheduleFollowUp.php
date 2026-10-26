<?php

namespace App\Mcp\Tools;

use App\CrmLeadActions;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('schedule_follow_up')]
#[Description('Schedule a follow-up after confirming the lead, responsible member, purpose and date/time. Use eligible member IDs from get_lead. Pending follow-ups send reminders when due and daily at 10 AM Asia/Kolkata. Reuse request_id and identical fields for retries.')]
#[IsReadOnly(false)]
#[IsOpenWorld(false)]
#[IsIdempotent]
#[IsDestructive(false)]
class ScheduleFollowUp extends CrmLeadTool
{
    protected function rules(): array
    {
        return ['lead_id' => ['required', 'integer', 'min:1'], 'request_id' => ['required', 'uuid'], 'responsible_id' => ['required', 'integer', 'min:1'], 'due_at' => ['required', 'date_format:Y-m-d\TH:iP'], 'notes' => ['required', 'string', 'max:5000']];
    }

    protected function perform(CrmLeadActions $actions, User $user, array $input): array
    {
        return $actions->schedule($user, (int) $input['lead_id'], $input);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->min(1)->required(),
            'request_id' => $schema->string()->description('UUID for this submission; reuse on identical retries.')->required(),
            'responsible_id' => $schema->integer()->min(1)->description('Confirmed eligible member ID from get_lead.')->required(),
            'due_at' => $schema->string()->description('Future date/time with explicit offset, e.g. 2026-12-01T10:00+05:30. Confirm ambiguous dates and times.')->required(),
            'notes' => $schema->string()->description('Confirmed purpose, maximum 5000 characters.')->required(),
        ];
    }
}
