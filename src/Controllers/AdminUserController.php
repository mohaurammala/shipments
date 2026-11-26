<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Enums\Role;
use App\Domain\User;
use App\Http\Flash;
use App\Http\HttpException;
use App\Http\Pages;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Services\Authenticator;
use App\Services\Exceptions\UserManagementException;
use App\Services\UserService;
use App\Validation\UserValidator;
use App\Validation\ValidationResult;

/**
 * /admin/users. Routed behind RequireLogin and RequireRole(Admin); every change also
 * needs the CSRF token. Each change ends in a redirect with a flash message.
 */
final readonly class AdminUserController
{
    public function __construct(
        private Pages $pages,
        private UserService $service,
        private UserRepository $users,
        private UserValidator $validator,
        private Authenticator $authenticator,
        private Flash $flash,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->pages->render('admin/users/index', [
            'title' => 'Users',
            'users' => $this->users->summaries(),
            'roles' => Role::cases(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form();
    }

    public function store(Request $request): Response
    {
        $input = [
            'email' => $request->input('email') ?? '',
            'role' => $request->input('role') ?? '',
            'password' => $request->secret('password') ?? '',
            'password_confirmation' => $request->secret('password_confirmation') ?? '',
        ];

        $result = $this->validator->validateNewUser($input);

        if (!$result->isValid()) {
            return $this->form($input, $result, 422);
        }

        try {
            $user = $this->service->create($this->admin(), $input['email'], $input['password'], Role::from($input['role']));
        } catch (UserManagementException $e) {
            return $this->form($input, $result->withError('email', $e->getMessage()), 422);
        }

        $this->flash->success("Account created for {$user->email}. Give them their password in person, not by email.");

        return Response::redirect('/admin/users');
    }

    public function changeRole(Request $request): Response
    {
        $target = $this->target($request);
        $role = Role::tryFrom($request->input('role') ?? '') ?? throw HttpException::badRequest();
        $admin = $this->admin();

        try {
            $this->service->changeRole($admin, $target, $role);
        } catch (UserManagementException $e) {
            return $this->back($e->getMessage(), success: false);
        }

        if ($target->id === $admin->id) {
            // Their own privileges changed: new session id, and leave the admin pages.
            $this->authenticator->reissueSession();
            $this->flash->success("Your role is now {$role->value}.");

            return Response::redirect('/');
        }

        return $this->back("{$target->email} is now {$role->value}.");
    }

    public function deactivate(Request $request): Response
    {
        $target = $this->target($request);

        try {
            $this->service->deactivate($this->admin(), $target);
        } catch (UserManagementException $e) {
            return $this->back($e->getMessage(), success: false);
        }

        return $this->back("{$target->email} is deactivated and signed out.");
    }

    public function reactivate(Request $request): Response
    {
        $target = $this->target($request);
        $this->service->reactivate($this->admin(), $target);

        return $this->back("{$target->email} can sign in again.");
    }

    /**
     * RequireRole has already checked this is a signed-in admin.
     */
    private function admin(): User
    {
        return $this->authenticator->user() ?? throw new HttpException(403, 'You do not have access to that page.');
    }

    private function target(Request $request): User
    {
        $id = $request->routeParam('id') ?? '';

        if (!ctype_digit($id)) {
            throw HttpException::notFound();
        }

        return $this->users->findById((int) $id) ?? throw HttpException::notFound();
    }

    private function back(string $message, bool $success = true): Response
    {
        $success ? $this->flash->success($message) : $this->flash->error($message);

        return Response::redirect('/admin/users');
    }

    /**
     * @param array{email: string, role: string, password: string, password_confirmation: string}|null $input
     */
    private function form(?array $input = null, ?ValidationResult $result = null, int $status = 200): Response
    {
        return $this->pages->render('admin/users/create', [
            'title' => 'Add a user',
            'email' => $input['email'] ?? '',
            'role' => $input['role'] ?? Role::Operator->value,
            'roles' => Role::cases(),
            'errors' => $result ?? new ValidationResult(),
        ], $status);
    }
}
