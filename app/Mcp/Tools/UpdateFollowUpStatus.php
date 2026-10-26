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

#[Name('update_follow_up_status')]
#[Description('Complete or cancel a specific follow-up after user confirmation. Read get_lead first and use its follow-up ID. This stops future reminders. Identical retries are safe; a closed follow-up cannot change to a different status.')]
#[IsReadOnly(false)]
#[IsOpenWorld(false)]
#[IsIdempotent]
#[IsDestructive(false)]
class UpdateFollowUpStatus extends CrmLeadTool
{
    protected function rules(): array
    {
        return ['lead_id' => ['required', 'integer', 'min:1'], 'follow_up_id' => ['required', 'integer', 'min:1'], 'status' => ['required', 'in:completed,cancelled']];
    }

    protected function perform(CrmLeadActions $actions, User $user, array $input): array
    {
        return $actions->updateFollowUp($user, (int) $input['lead_id'], (int) $input['follow_up_id'], $input);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->min(1)->required(),
            'follow_up_id' => $schema->integer()->min(1)->description('Follow-up ID returned by get_lead or schedule_follow_up.')->required(),
            'status' => $schema->string()->enum(['completed', 'cancelled'])->required(),
        ];
    }
}
