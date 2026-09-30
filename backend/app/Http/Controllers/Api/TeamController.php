<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {
    }

    public function index(PaginationRequest $request): ApiResponse
    {
        $this->authorize('manage-teams');

        $data = $request->validated();
        $query = Team::query()->withCount('users');

        if (! empty($data['q'])) {
            $search = str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $data['q']
            );

            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$search.'%']);
        }

        $teams = $query
            ->orderBy('name')
            ->paginate($data['per_page'] ?? 15);

        return ApiResponse::success(
            TeamResource::collection($teams->getCollection()),
            'Teams retrieved successfully.',
            [
                'current_page' => $teams->currentPage(),
                'last_page' => $teams->lastPage(),
                'per_page' => $teams->perPage(),
                'total' => $teams->total(),
                'from' => $teams->firstItem(),
                'to' => $teams->lastItem(),
            ]
        );
    }

    public function show(Team $team): ApiResponse
    {
        $this->authorize('manage-teams');

        return ApiResponse::success(
            new TeamResource($team->loadCount('users')),
            'Team retrieved successfully.'
        );
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $this->authorize('manage-teams');

        $team = DB::transaction(function () use ($request): Team {
            $team = Team::query()->create($request->validated());

            $this->activityLogger->log(
                action: 'team_created',
                description: 'Team created: '.$team->name.'.',
                actor: $request->user(),
                newValues: $team->only(['id', 'name'])
            );

            return $team;
        });

        return ApiResponse::success(
            new TeamResource($team->loadCount('users')),
            'Team created successfully.'
        )->response()->setStatusCode(201);
    }

    public function update(
        StoreTeamRequest $request,
        Team $team
    ): ApiResponse {
        $this->authorize('manage-teams');

        $team = DB::transaction(function () use ($request, $team): Team {
            $team = Team::query()->lockForUpdate()->findOrFail($team->id);
            $before = $team->name;
            $team->fill($request->validated());

            if ($team->isDirty()) {
                $team->save();

                $this->activityLogger->log(
                    action: 'team_updated',
                    description: 'Team details updated.',
                    actor: $request->user(),
                    oldValues: ['name' => $before],
                    newValues: ['id' => $team->id, 'name' => $team->name]
                );
            }

            return $team;
        });

        return ApiResponse::success(
            new TeamResource($team->loadCount('users')),
            'Team updated successfully.'
        );
    }

    public function destroy(Request $request, Team $team): ApiResponse
    {
        $this->authorize('manage-teams');

        DB::transaction(function () use ($request, $team): void {
            $team = Team::query()->lockForUpdate()->findOrFail($team->id);

            $this->activityLogger->log(
                action: 'team_deleted',
                description: 'Team deleted: '.$team->name.'.',
                actor: $request->user(),
                oldValues: $team->only(['id', 'name'])
            );

            // The foreign key sets affected users' team_id to null.
            $team->delete();
        });

        return ApiResponse::success(null, 'Team deleted successfully.');
    }
}
