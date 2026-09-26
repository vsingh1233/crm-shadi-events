<?php

namespace App\Mcp\Tools;

use App\CreateLead;
use App\Http\Requests\StoreApiLeadRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create_whatsapp_lead')]
#[Description(
    'Create a WhatsApp lead after the user confirms the extracted details. '
    .'Requires a name and either email or phone. Saves extra information '
    .'as a discussion note. Reuse the request_id and identical input when '
    .'retrying. Duplicate warnings require review in the CRM.'
)]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
class CreateWhatsappLead extends Tool
{
    public function handle(
        Request $request,
        CreateLead $creator
    ): Response|ResponseFactory {
        $authenticatedUser = $request->user('mcp');

        if (! $authenticatedUser instanceof User) {
            return Response::error('Sign in to the CRM connection first.');
        }

        if (! $authenticatedUser->tokenCan('mcp:use')) {
            return Response::error(
                'This connection does not have the required MCP scope.'
            );
        }

        try {
            Gate::forUser($authenticatedUser)
                ->authorize('createViaApi', Lead::class);

            $input = $this->prepareInput($request);

            $formRequest = StoreApiLeadRequest::create(
                '/',
                'POST',
                $input
            );

            $data = Validator::make(
                $input,
                $formRequest->rules()
            )->validate();

            $requestId = strtolower($data['request_id']);

            unset($data['request_id']);

            ksort($data);

            $payloadHash = hash(
                'sha256',
                'mcp:whatsapp:'.json_encode($data, JSON_THROW_ON_ERROR)
            );

            $result = DB::transaction(function () use (
                $authenticatedUser,
                $creator,
                $data,
                $requestId,
                $payloadHash
            ): array {
                $user = User::query()
                    ->whereKey($authenticatedUser->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                Gate::forUser($user)
                    ->authorize('createViaApi', Lead::class);

                $existing = Lead::withTrashed()
                    ->where('created_by', $user->id)
                    ->where('api_request_id', $requestId)
                    ->first();

                if ($existing !== null) {
                    if ($existing->trashed()) {
                        throw ValidationException::withMessages([
                            'request_id' => [
                                'This request created a lead that was later '
                                .'deleted. Review it in the CRM.',
                            ],
                        ]);
                    }

                    Gate::forUser($user)->authorize('view', $existing);

                    if (! hash_equals(
                        (string) $existing->api_payload_hash,
                        $payloadHash
                    )) {
                        throw ValidationException::withMessages([
                            'request_id' => [
                                'This request ID was already used with '
                                .'different data. Review the earlier result '
                                .'before making another submission.',
                            ],
                        ]);
                    }

                    return $this->leadResult($existing, true);
                }

                $lead = $creator->create(
                    $data,
                    $user,
                    'whatsapp',
                    'Created through the CRM MCP connection'
                );

                $lead->api_request_id = $requestId;
                $lead->api_payload_hash = $payloadHash;
                $lead->save();

                return $this->leadResult($lead, false);
            });

            return Response::structured($result);
        } catch (ValidationException $exception) {
            return Response::error(json_encode([
                'success' => false,
                'errors' => $exception->errors(),
            ], JSON_THROW_ON_ERROR));
        } catch (AuthorizationException) {
            return Response::error(
                'Your CRM account is not authorized for this operation.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareInput(Request $request): array
    {
        $input = [
            'request_id' => $request->get('request_id'),
            'status' => 'new',
            'source' => 'whatsapp',
        ];

        foreach ([
            'name',
            'email',
            'phone',
            'client_location',
            'wedding_location',
            'wedding_start_date',
            'wedding_end_date',
            'notes',
        ] as $field) {
            $value = $request->get($field);

            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            $input[$field] = $value;
        }

        if (is_string($input['email'])) {
            $input['email'] = strtolower($input['email']);
        }

        return $input;
    }

    /**
     * @return array{
     *     success: bool,
     *     replayed: bool,
     *     lead_id: int,
     *     name: string,
     *     url: string
     * }
     */
    private function leadResult(Lead $lead, bool $replayed): array
    {
        return [
            'success' => true,
            'replayed' => $replayed,
            'lead_id' => (int) $lead->id,
            'name' => $lead->name,
            'url' => route('leads.show', $lead),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->string()
                ->description(
                    'A UUID for this submission. Reuse it with identical '
                    .'fields when retrying the same save.'
                )
                ->required(),

            'name' => $schema->string()
                ->description('The client name confirmed by the user.')
                ->required(),

            'email' => $schema->string()
                ->description(
                    'Client email. At least one of email or phone is required.'
                )
                ->nullable(),

            'phone' => $schema->string()
                ->description(
                    'Client phone number. Include country code only when known.'
                )
                ->nullable(),

            'client_location' => $schema->string()
                ->description('Where the client lives.')
                ->nullable(),

            'wedding_location' => $schema->string()
                ->description('The requested wedding destination or venue.')
                ->nullable(),

            'wedding_start_date' => $schema->string()
                ->description(
                    'Confirmed start date in YYYY-MM-DD format; otherwise null.'
                )
                ->nullable(),

            'wedding_end_date' => $schema->string()
                ->description(
                    'Confirmed end date in YYYY-MM-DD format; otherwise null.'
                )
                ->nullable(),

            'notes' => $schema->string()
                ->description(
                    'Relevant extra details and conversation summary. '
                    .'Maximum 10000 characters.'
                )
                ->nullable(),
        ];
    }
}
