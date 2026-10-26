<?php

namespace App\Mcp\Tools;

use App\CrmLeadActions;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_leads')]
#[Description('Search accessible CRM leads by client, couple names in either order, email or phone. Results are paginated. Select the correct lead before any changes; do not guess IDs.')]
#[IsReadOnly]
#[IsOpenWorld(false)]
class SearchLeads extends CrmLeadTool
{
    protected function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string'], 'page' => ['sometimes', 'integer', 'min:1']];
    }

    protected function perform(CrmLeadActions $actions, User $user, array $input): array
    {
        return $actions->search($user, $input);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Client, couple name, email or phone. Omit to list accessible leads.'),
            'status' => $schema->string()->enum(array_keys(Lead::STATUSES))->description('Optional lead sales status filter.'),
            'page' => $schema->integer()->min(1)->description('Page number, starting at 1. Each page returns up to 20 leads.'),
        ];
    }
}
