<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManageLeadApiToken extends Command
{
    protected $signature = 'crm:lead-api-token
        {email : Existing CRM user email}
        {--days=30 : Token lifetime in days}
        {--revoke : Revoke the token and API permission}';

    protected $description = 'Grant or revoke lead API access for a CRM user';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if (! $this->option('revoke')
            && ($days === false || $days < 1 || $days > 365)) {
            $this->error('Choose a token lifetime between 1 and 365 days.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($email, $days): int {
            $user = User::where('email', $email)->lockForUpdate()->first();

            if (! $user) {
                $this->error('CRM user not found.');

                return self::FAILURE;
            }

            if ($this->option('revoke')) {
                $user->can_create_leads_via_api = false;
                $user->lead_api_token_hash = null;
                $user->lead_api_token_expires_at = null;
                $user->save();

                $this->info('API access revoked.');

                return self::SUCCESS;
            }

            if (! $user->is_active
                || ! in_array($user->role, ['administrator', 'team_member'], true)) {
                $this->error('Only active administrators or team members can receive access.');

                return self::FAILURE;
            }

            $token = bin2hex(random_bytes(32));

            $user->can_create_leads_via_api = true;
            $user->lead_api_token_hash = hash('sha256', $token);
            $user->lead_api_token_expires_at = now()->addDays($days);
            $user->save();

            $this->info('New token issued. Any previous token is invalid.');
            $this->line('Expires: '.$user->lead_api_token_expires_at->toIso8601String());
            $this->warn('Copy this token securely. It is displayed only now.');
            $this->line($token);

            return self::SUCCESS;
        });
    }
}