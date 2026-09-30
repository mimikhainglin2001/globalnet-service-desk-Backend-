<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Plain CRUD with no business rules, so it talks to Eloquent directly
 * instead of going through a service/repository layer.
 */
class TeamController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TeamResource::collection(
            Team::query()->with('users')->withCount('users')->orderBy('name')->paginate(50)
        );
    }

    public function store(TeamRequest $request): JsonResponse
    {
        $team = DB::transaction(function () use ($request) {
            $team = Team::create($request->safe()->only(['name', 'description']));
            $team->users()->sync($request->validated('member_ids', []));

            return $team;
        });

        return (new TeamResource($team->load('users')->loadCount('users')))->response()->setStatusCode(201);
    }

    public function show(Team $team): TeamResource
    {
        return new TeamResource($team->load('users')->loadCount('users'));
    }

    public function update(TeamRequest $request, Team $team): TeamResource
    {
        DB::transaction(function () use ($request, $team) {
            $team->update($request->safe()->only(['name', 'description']));

            if ($request->has('member_ids')) {
                $team->users()->sync($request->validated('member_ids', []));
            }
        });

        return new TeamResource($team->load('users')->loadCount('users'));
    }

    public function destroy(Team $team): Response
    {
        $team->delete();

        return response()->noContent();
    }
}
