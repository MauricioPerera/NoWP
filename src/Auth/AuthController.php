<?php

declare(strict_types=1);

namespace ChimeraNoWP\Auth;

use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\Exceptions\AuthenticationException;
use ChimeraNoWP\Core\Exceptions\ValidationException;

class AuthController
{
    public function __construct(
        private UserService $userService,
        private JWTManager $jwtManager
    ) {}

    /**
     * POST /api/auth/login
     */
    public function login(Request $request): Response
    {
        try {
            $data = $request->json();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($email) || empty($password)) {
                return Response::error('Email and password are required', 'VALIDATION_ERROR', 400);
            }

            $result = $this->userService->login($email, $password);

            return Response::success($result, 'Login successful');
        } catch (AuthenticationException $e) {
            return Response::error($e->getMessage(), 'INVALID_CREDENTIALS', 401);
        } catch (\Exception $e) {
            return Response::error('Login failed', 'LOGIN_ERROR', 500);
        }
    }

    /**
     * POST /api/auth/register
     */
    public function register(Request $request): Response
    {
        try {
            $data = $request->json();

            // Public registration always creates subscribers
            $data['role'] = UserRole::SUBSCRIBER->value;

            $user = $this->userService->register($data);

            // Auto-login after registration
            $token = $this->jwtManager->generateToken(
                $user->id,
                $user->email,
                $user->role->value
            );

            return Response::success([
                'token' => $token,
                'user' => $user->toArray(),
            ], 'Registration successful', 201);
        } catch (ValidationException $e) {
            return Response::error(
                'Validation failed',
                'VALIDATION_ERROR',
                422,
                $e->getErrors()
            );
        } catch (\Exception $e) {
            return Response::error('Registration failed', 'REGISTRATION_ERROR', 500);
        }
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return Response::error('Not authenticated', 'AUTHENTICATION_REQUIRED', 401);
        }

        return Response::success($user->toArray(), 'User profile retrieved');
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh(Request $request): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return Response::error('Not authenticated', 'AUTHENTICATION_REQUIRED', 401);
        }

        $token = $this->jwtManager->generateToken(
            $user->id,
            $user->email,
            $user->role->value
        );

        return Response::success([
            'token' => $token,
            'user' => $user->toArray(),
        ], 'Token refreshed');
    }

    /**
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request): Response
    {
        try {
            $user = $request->user();

            if (!$user instanceof User) {
                return Response::error('Not authenticated', 'AUTHENTICATION_REQUIRED', 401);
            }

            $data = $request->json();
            $current = $data['current_password'] ?? '';
            $new = $data['new_password'] ?? '';

            if (empty($current) || empty($new)) {
                return Response::error(
                    'Current password and new password are required',
                    'VALIDATION_ERROR',
                    400
                );
            }

            $this->userService->changePassword($user->id, $current, $new);

            return Response::success(null, 'Password changed successfully');
        } catch (AuthenticationException $e) {
            return Response::error($e->getMessage(), 'INVALID_PASSWORD', 401);
        } catch (ValidationException $e) {
            return Response::error('Validation failed', 'VALIDATION_ERROR', 422, $e->getErrors());
        } catch (\Exception $e) {
            return Response::error('Password change failed', 'PASSWORD_ERROR', 500);
        }
    }
}
