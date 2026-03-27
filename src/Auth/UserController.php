<?php

declare(strict_types=1);

namespace ChimeraNoWP\Auth;

use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\Exceptions\NotFoundException;
use ChimeraNoWP\Core\Exceptions\ValidationException;
use ChimeraNoWP\Core\Exceptions\AuthorizationException;

class UserController
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * GET /api/users
     * Requires: user.read permission
     */
    public function index(Request $request): Response
    {
        try {
            $filters = [
                'limit' => min((int) $request->query('per_page', 20), 100),
                'offset' => ((int) $request->query('page', 1) - 1) * min((int) $request->query('per_page', 20), 100),
            ];

            if ($role = $request->query('role')) {
                $filters['role'] = $role;
            }
            if ($search = $request->query('search')) {
                $filters['search'] = $search;
            }
            if ($orderBy = $request->query('order_by')) {
                $filters['order_by'] = $orderBy;
            }

            $result = $this->userService->listUsers($filters);

            return Response::success([
                'users' => array_map(fn(User $u) => $u->toArray(), $result['users']),
                'total' => $result['total'],
                'page' => (int) $request->query('page', 1),
                'per_page' => $filters['limit'],
            ], 'Users retrieved');
        } catch (\Exception $e) {
            return Response::error('Failed to retrieve users', 'USER_LIST_ERROR', 500);
        }
    }

    /**
     * GET /api/users/{id}
     * Requires: user.read permission
     */
    public function show(Request $request, int $id): Response
    {
        try {
            $user = $this->userService->getUser($id);
            return Response::success($user->toArray(), 'User retrieved');
        } catch (NotFoundException $e) {
            return Response::error('User not found', 'USER_NOT_FOUND', 404);
        } catch (\Exception $e) {
            return Response::error('Failed to retrieve user', 'USER_ERROR', 500);
        }
    }

    /**
     * POST /api/users
     * Requires: user.create permission (admin only)
     */
    public function store(Request $request): Response
    {
        try {
            $data = $request->json();
            $user = $this->userService->register($data);

            return Response::success($user->toArray(), 'User created', 201);
        } catch (ValidationException $e) {
            return Response::error('Validation failed', 'VALIDATION_ERROR', 422, $e->getErrors());
        } catch (\Exception $e) {
            return Response::error('Failed to create user', 'USER_CREATE_ERROR', 500);
        }
    }

    /**
     * PUT /api/users/{id}
     * Requires: user.update permission or own profile
     */
    public function update(Request $request, int $id): Response
    {
        try {
            $data = $request->json();
            $actor = $request->user();

            $user = $this->userService->updateUser($id, $data, $actor instanceof User ? $actor : null);

            return Response::success($user->toArray(), 'User updated');
        } catch (NotFoundException $e) {
            return Response::error('User not found', 'USER_NOT_FOUND', 404);
        } catch (ValidationException $e) {
            return Response::error('Validation failed', 'VALIDATION_ERROR', 422, $e->getErrors());
        } catch (AuthorizationException $e) {
            return Response::error($e->getMessage(), 'FORBIDDEN', 403);
        } catch (\Exception $e) {
            return Response::error('Failed to update user', 'USER_UPDATE_ERROR', 500);
        }
    }

    /**
     * DELETE /api/users/{id}
     * Requires: user.delete permission (admin only)
     */
    public function destroy(Request $request, int $id): Response
    {
        try {
            $actor = $request->user();
            $this->userService->deleteUser($id, $actor instanceof User ? $actor : null);

            return Response::success(null, 'User deleted', 204);
        } catch (NotFoundException $e) {
            return Response::error('User not found', 'USER_NOT_FOUND', 404);
        } catch (ValidationException $e) {
            return Response::error('Validation failed', 'VALIDATION_ERROR', 422, $e->getErrors());
        } catch (\Exception $e) {
            return Response::error('Failed to delete user', 'USER_DELETE_ERROR', 500);
        }
    }
}
