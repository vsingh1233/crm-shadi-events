<?php

use App\Mcp\Servers\CrmServer;
use App\Models\Lead;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

Mcp::oauthRoutes();

Mcp::web('/mcp/crm', CrmServer::class)
    ->middleware([
        'auth:mcp',
        CheckToken::using('mcp:use'),
        'can:createViaApi,'.Lead::class,
        'throttle:60,1',
    ]);
