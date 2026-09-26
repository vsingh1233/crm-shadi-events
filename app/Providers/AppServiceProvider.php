<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::viaRequest('lead-api', function (Request $request): ?User {
            $token = $request->bearerToken();

            if (! is_string($token)
                || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
                return null;
            }

            return User::query()
                ->where('lead_api_token_hash', hash('sha256', $token))
                ->where('lead_api_token_expires_at', '>', now())
                ->first();
        });
    }
}