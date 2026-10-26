<?php

namespace App\Mcp\Tools;

use App\CrmLeadActions;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_lead')]
#[Description('Read an accessible lead, its follow-ups and eligible responsible team members. Use returned IDs and current values for updates. Follow-ups are paginated with pending ones first.')]
#[IsReadOnly]
#[IsOpenWorld(false)]
class GetLead extends CrmLeadTool
{
    protected function rules(): array
    {
        return ['lead_id' => ['required', 'integer', 'min:1'], 'page' => ['sometimes', 'integer', 'min:1']];
    }

    protected function perform(CrmLeadActions $actions, User $user, array $input): array
    {
        return $actions->details($user, (int) $input['lead_id'], (int) ($input['page'] ?? 1));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->min(1)->description('Lead ID returned by CRM search or creation.')->required(),
            'page' => $schema->integer()->min(1)->description('Follow-up page number, starting at 1.'),
        ];
    }
}
