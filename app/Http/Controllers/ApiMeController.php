<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use Illuminate\Http\Request;
use LaIOT\Http\Controller;
use LaIOT\Services\Auth\RoleRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Минимальный защищённый API-эндпоинт для проверки токена и прав.
 */
final readonly class ApiMeController extends Controller
{
    public function __construct(private RoleRepository $roles)
    {
    }

    public function __invoke(Request $request): Response
    {
        $userId = (int)$request->attributes->get('api_user_id', 0);

        return $this->json([
            'user_id' => $userId,
            'roles' => $this->roles->rolesForUser($userId),
            'permissions' => $this->roles->permissionsForUser($userId),
        ]);
    }
}
