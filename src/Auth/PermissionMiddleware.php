<?php

namespace Framework\Auth;

use Framework\Core\MiddlewareInterface;
use Framework\Core\Request;
use Framework\Core\Response;

class PermissionMiddleware implements MiddlewareInterface
{
    /**
     * @param string|array $permissions Required permission(s)
     */
    public function __construct(
        private string|array $permissions
    ) {}

    /**
     * Handle the request
     */
    public function handle(Request $request, callable $next): Response
    {
        $user = $request->getAttribute('user');

        if (!$user instanceof User) {
            return new Response(
                json_encode([
                    'error' => [
                        'code' => 'AUTHENTICATION_REQUIRED',
                        'message' => 'Authentication is required to access this resource'
                    ]
                ]),
                401,
                ['Content-Type' => 'application/json']
            );
        }

        $permissions = is_array($this->permissions) ? $this->permissions : [$this->permissions];

        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                return new Response(
                    json_encode([
                        'error' => [
                            'code' => 'INSUFFICIENT_PERMISSIONS',
                            'message' => 'You do not have permission to access this resource',
                            'details' => [
                                'required_permission' => $permission,
                                'user_role' => $user->role->value
                            ]
                        ]
                    ]),
                    403,
                    ['Content-Type' => 'application/json']
                );
            }
        }

        return $next($request);
    }
}
