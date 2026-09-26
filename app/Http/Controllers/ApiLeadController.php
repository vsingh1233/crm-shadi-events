<?php

namespace App\Http\Controllers;

use App\CreateLead;
use App\Http\Requests\StoreApiLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApiLeadController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'can_create_leads' => true,
            ],
        ]);
    }

    public function store(
        StoreApiLeadRequest $request,
        CreateLead $creator
    ): JsonResponse {
        $data = $request->validated();
        $requestId = strtolower($data['request_id']);

        unset($data['request_id']);

        ksort($data);

        $payloadHash = hash(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        return DB::transaction(function () use (
            $request,
            $creator,
            $data,
            $requestId,
            $payloadHash
        ): JsonResponse {
            $user = User::whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $user->lead_api_token_hash !== null
                && hash_equals(
                    $user->lead_api_token_hash,
                    hash('sha256', (string) $request->bearerToken())
                )
                && $user->lead_api_token_expires_at?->isFuture(),
                401,
                'The API token has expired or been revoked.'
            );

            Gate::forUser($user)->authorize('createViaApi', Lead::class);

            $existing = Lead::withTrashed()
                ->where('created_by', $user->id)
                ->where('api_request_id', $requestId)
                ->first();

            if ($existing) {
                abort_if(
                    $existing->trashed(),
                    409,
                    'This request already created a lead that was subsequently deleted.'
                );

                Gate::forUser($user)->authorize('view', $existing);

                abort_unless(
                    hash_equals(
                        (string) $existing->api_payload_hash,
                        $payloadHash
                    ),
                    409,
                    'This request ID was already used with different data.'
                );

                return (new LeadResource($existing))
                    ->additional(['meta' => ['replayed' => true]])
                    ->response()
                    ->setStatusCode(200);
            }

            $lead = $creator->create(
                $data,
                $user,
                $data['source'],
                'Created through lead API'
            );

            $lead->api_request_id = $requestId;
            $lead->api_payload_hash = $payloadHash;
            $lead->save();

            return (new LeadResource($lead))
                ->additional(['meta' => ['replayed' => false]])
                ->response()
                ->setStatusCode(201);
        });
    }
}