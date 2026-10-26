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

#[Name('update_couple_name')]
#[Description('Update or clear a lead couple name after the user confirms the lead and new value. Pass the exact previous couple name from get_lead to detect concurrent edits. Retry with identical fields.')]
#[IsReadOnly(false)]
#[IsOpenWorld(false)]
#[IsIdempotent]
#[IsDestructive(false)]
class UpdateCoupleName extends CrmLeadTool
{
    protected function rules(): array
    {
        return ['lead_id' => ['required', 'integer', 'min:1'], 'couple_name' => ['present', 'nullable', 'string', 'max:255'], 'expected_couple_name' => ['present', 'nullable', 'string', 'max:255']];
    }

    protected function perform(CrmLeadActions $actions, User $user, array $input): array
    {
        return $actions->updateCoupleName($user, (int) $input['lead_id'], $input);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->min(1)->required(),
            'couple_name' => $schema->string()->nullable()->description('Confirmed couple name in the supplied order; null clears it.')->required(),
            'expected_couple_name' => $schema->string()->nullable()->description('Exact current value returned by get_lead, including null.')->required(),
        ];
    }
}
