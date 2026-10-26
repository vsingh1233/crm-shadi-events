<?php

namespace App\Mcp\Tools;

use App\CrmLeadActions;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

abstract class CrmLeadTool extends Tool
{
    public function handle(Request $request, CrmLeadActions $actions): Response|ResponseFactory
    {
        $authenticatedUser = $request->user('mcp');
        if (! $authenticatedUser instanceof User || ! $authenticatedUser->tokenCan('mcp:use')) {
            return Response::error('Sign in with a CRM connection that has the mcp:use scope.');
        }

        try {
            $user = User::findOrFail($authenticatedUser->id);
            $actions->authorize($user);
            $input = Validator::make($request->all(), $this->rules())->validate();

            return Response::structured(['success' => true] + $this->perform($actions, $user, $input));
        } catch (ValidationException $exception) {
            return Response::error(json_encode(['success' => false, 'errors' => $exception->errors()], JSON_THROW_ON_ERROR));
        } catch (AuthorizationException) {
            return Response::error('Your CRM account is not authorized for this operation.');
        } catch (ModelNotFoundException) {
            return Response::error('The requested record was not found or is not accessible to you.');
        } catch (HttpExceptionInterface $exception) {
            return Response::error(json_encode(['success' => false, 'status' => $exception->getStatusCode(), 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR));
        }
    }

    /** @return array<string, mixed> */
    abstract protected function rules(): array;

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    abstract protected function perform(CrmLeadActions $actions, User $user, array $input): array;
}
