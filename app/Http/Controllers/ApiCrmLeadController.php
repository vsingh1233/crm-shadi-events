<?php

namespace App\Http\Controllers;

use App\CrmLeadActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiCrmLeadController extends Controller
{
    public function __construct(private CrmLeadActions $actions) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->actions->search($request->user(), $request->all()));
    }

    public function show(Request $request, int $lead): JsonResponse
    {
        $data = $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return response()->json($this->actions->details($request->user(), $lead, (int) ($data['page'] ?? 1)));
    }

    public function updateCoupleName(Request $request, int $lead): JsonResponse
    {
        return response()->json($this->actions->updateCoupleName($request->user(), $lead, $request->all()));
    }

    public function schedule(Request $request, int $lead): JsonResponse
    {
        $result = $this->actions->schedule($request->user(), $lead, $request->all());

        return response()->json($result, $result['meta']['replayed'] ? 200 : 201);
    }

    public function updateFollowUp(Request $request, int $lead, int $followUp): JsonResponse
    {
        return response()->json($this->actions->updateFollowUp($request->user(), $lead, $followUp, $request->all()));
    }
}
