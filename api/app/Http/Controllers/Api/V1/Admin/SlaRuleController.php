<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SlaRuleRequest;
use App\Http\Resources\SlaRuleResource;
use App\Models\SlaRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Changes apply to tickets created (or re-prioritised) afterwards; existing
 * due dates are not recalculated retroactively.
 */
class SlaRuleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SlaRuleResource::collection(
            SlaRule::query()->get()->sortBy(fn (SlaRule $rule) => $rule->priority->weight())->values()
        );
    }

    public function store(SlaRuleRequest $request): JsonResponse
    {
        return (new SlaRuleResource(SlaRule::create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(SlaRuleRequest $request, SlaRule $slaRule): SlaRuleResource
    {
        $slaRule->update($request->validated());

        return new SlaRuleResource($slaRule);
    }

    public function destroy(SlaRule $slaRule): Response
    {
        $slaRule->delete();

        return response()->noContent();
    }
}
