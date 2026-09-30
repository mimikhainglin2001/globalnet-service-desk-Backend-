<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommentType;
use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Reference data for forms and filters, so the SPA does not hardcode
 * statuses, priorities, categories or limits.
 */
class LookupController extends Controller
{
    public function meta(): JsonResponse
    {
        $options = fn (array $cases) => array_map(
            fn (BackedEnum $case) => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );

        return response()->json([
            'data' => [
                'statuses' => $options(TicketStatus::cases()),
                'priorities' => $options(TicketPriority::cases()),
                'roles' => $options(Role::cases()),
                'comment_types' => $options(CommentType::cases()),
                'attachments' => [
                    'max_files' => config('servicedesk.attachments.max_files'),
                    'max_size_kb' => config('servicedesk.attachments.max_size_kb'),
                    'allowed_types' => config('servicedesk.attachments.allowed_mimes'),
                ],
                'reopen_window_days' => config('servicedesk.tickets.reopen_window_days'),
            ],
        ]);
    }

    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->active()->orderBy('name')->get());
    }

    public function teams(): AnonymousResourceCollection
    {
        return TeamResource::collection(Team::query()->orderBy('name')->get());
    }

    /**
     * Assignable staff for filters and the assignee picker (staff only).
     */
    public function agents(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()->staff()->with('teams')->orderBy('name')->get()
        );
    }
}
