<?php

declare(strict_types=1);

namespace Core\Users\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Users\Application\DTOs\CreateUserData;
use Core\Users\Application\UserService;
use Core\Users\Http\Requests\CreateUserRequest;
use Core\Users\Http\Requests\UpdateUserRequest;
use Core\Users\Http\Resources\UserResource;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deliberately thin — see docs/architecture/clean-architecture.md.
 * All business logic lives in UserService.
 */
final class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = User::query()
            ->when($request->string('filter.status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('filter.status')->value()))
            ->orderBy($request->string('sort', 'created_at')->trim('-')->value(), $request->string('sort')->startsWith('-') ? 'desc' : 'asc')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->ok(UserResource::collection($paginated));
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->users->create(new CreateUserData(
            name: $request->string('name')->value(),
            email: $request->string('email')->value(),
            password: $request->string('password')->value(),
            timezone: $request->string('timezone', 'UTC')->value(),
            locale: $request->string('locale', 'en')->value(),
        ));

        return $this->created(new UserResource($user));
    }

    public function show(User $user): JsonResponse
    {
        return $this->ok(new UserResource($user->load('roles')));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return $this->ok(new UserResource($user));
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $this->users->suspend($user, $request->string('reason')->value() ?: null);

        return $this->ok(new UserResource($user->fresh()));
    }

    public function reactivate(Request $request, User $user): JsonResponse
    {
        $this->users->reactivate($user);

        return $this->ok(new UserResource($user->fresh()));
    }

    public function unlock(Request $request, User $user): JsonResponse
    {
        $this->users->unlock($user);

        return $this->ok(new UserResource($user->fresh()));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return $this->noContent();
    }
}
