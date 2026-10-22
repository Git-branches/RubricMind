<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class Users extends BaseController
{
    private const PER_PAGE = 20;

    private UserModel $users;

    public function __construct()
    {
        $this->users = model(UserModel::class);
    }

    public function index(): string
    {
        $filters = [
            'role'   => (string) $this->request->getGet('role'),
            'status' => (string) $this->request->getGet('status'),
            'q'      => trim((string) $this->request->getGet('q')),
        ];

        if (in_array($filters['role'], UserModel::ROLES, true)) {
            $this->users->where('role', $filters['role']);
        }

        if (in_array($filters['status'], UserModel::STATUSES, true)) {
            $this->users->where('status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $this->users->groupStart()
                ->like('name', $filters['q'])
                ->orLike('email', $filters['q'])
                ->groupEnd();
        }

        return view('admin/users/index', [
            'users'   => $this->users->orderBy('name')->paginate(self::PER_PAGE),
            'pager'   => $this->users->pager,
            'filters' => $filters,
        ]);
    }

    public function new(): string
    {
        return view('admin/users/form', ['user' => null]);
    }

    public function create(): RedirectResponse|string
    {
        $data = $this->postedUserData();
        $data['password']         = (string) $this->request->getPost('password');
        $data['password_confirm'] = (string) $this->request->getPost('password_confirm');

        $rules = $this->userRules() + $this->passwordRules();
        $rules['email']['rules'] .= '|is_unique[users.email]';

        if (! $this->validateData($data, $rules)) {
            return view('admin/users/form', ['user' => null, 'old' => $data, 'errors' => $this->validator->getErrors()]);
        }

        $id = $this->users->insert([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'role'                 => $data['role'],
            'password_hash'        => UserModel::hashPassword($data['password']),
            'status'               => 'active',
            'must_change_password' => true,
        ]);

        audit_log('user.created', 'user', $id, ['role' => $data['role']]);

        return redirect()->to(site_url('admin/users'))
            ->with('success', "Account created for {$data['name']}. They will choose a new password at first sign-in.");
    }

    public function edit(int $id): string
    {
        return view('admin/users/form', ['user' => $this->findOr404($id)]);
    }

    public function update(int $id): RedirectResponse|string
    {
        $user = $this->findOr404($id);
        $data = $this->postedUserData() + ['id' => $id];

        $rules = $this->userRules();
        $rules['id']              = 'required|is_natural_no_zero';
        $rules['email']['rules'] .= '|is_unique[users.email,id,{id}]';

        if (! $this->validateData($data, $rules)) {
            return view('admin/users/form', ['user' => $user, 'old' => $data, 'errors' => $this->validator->getErrors()]);
        }

        // An admin removing their own admin role could leave nobody able to manage accounts.
        if ($id === service('auth')->id() && $data['role'] !== 'admin') {
            return view('admin/users/form', [
                'user'   => $user,
                'old'    => $data,
                'errors' => ['role' => 'You cannot remove the administrator role from your own account.'],
            ]);
        }

        $changes = [];

        foreach (['name', 'email', 'role'] as $field) {
            if ($user[$field] !== $data[$field]) {
                $changes[$field] = ['from' => $user[$field], 'to' => $data[$field]];
            }
        }

        if ($changes !== []) {
            $this->users->update($id, array_map(static fn (array $change) => $change['to'], $changes));
            audit_log('user.updated', 'user', $id, $changes);
        }

        return redirect()->to(site_url('admin/users'))->with('success', "Account for {$data['name']} updated.");
    }

    public function toggleStatus(int $id): RedirectResponse
    {
        $user = $this->findOr404($id);

        if ($id === service('auth')->id()) {
            return redirect()->to(site_url('admin/users'))->with('error', 'You cannot deactivate your own account.');
        }

        $status = $user['status'] === 'active' ? 'inactive' : 'active';
        $this->users->update($id, ['status' => $status]);

        audit_log($status === 'active' ? 'user.activated' : 'user.deactivated', 'user', $id);

        $verb = $status === 'active' ? 'activated' : 'deactivated';

        return redirect()->to(site_url('admin/users'))->with('success', "Account for {$user['name']} {$verb}.");
    }

    public function resetPasswordForm(int $id): RedirectResponse|string
    {
        $user = $this->findOr404($id);

        if ($id === service('auth')->id()) {
            return redirect()->to(site_url('account/password'));
        }

        return view('admin/users/reset_password', ['user' => $user]);
    }

    public function resetPassword(int $id): RedirectResponse|string
    {
        $user = $this->findOr404($id);

        if ($id === service('auth')->id()) {
            return redirect()->to(site_url('account/password'));
        }

        $data = [
            'password'         => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
        ];

        if (! $this->validateData($data, $this->passwordRules())) {
            return view('admin/users/reset_password', ['user' => $user, 'errors' => $this->validator->getErrors()]);
        }

        $this->users->update($id, [
            'password_hash'        => UserModel::hashPassword($data['password']),
            'must_change_password' => true,
        ]);

        audit_log('user.password_reset', 'user', $id);

        return redirect()->to(site_url('admin/users'))
            ->with('success', "Temporary password set for {$user['name']}. They will choose a new one at next sign-in.");
    }

    private function findOr404(int $id): array
    {
        return $this->users->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }

    /**
     * @return array{name: string, email: string, role: string}
     */
    private function postedUserData(): array
    {
        return [
            'name'  => trim((string) $this->request->getPost('name')),
            'email' => strtolower(trim((string) $this->request->getPost('email'))),
            'role'  => (string) $this->request->getPost('role'),
        ];
    }

    private function userRules(): array
    {
        return [
            'name'  => ['label' => 'full name', 'rules' => 'required|max_length[100]'],
            'email' => ['label' => 'email', 'rules' => 'required|valid_email|max_length[191]'],
            'role'  => ['label' => 'role', 'rules' => 'required|in_list[' . implode(',', UserModel::ROLES) . ']'],
        ];
    }

    private function passwordRules(): array
    {
        return [
            'password'         => ['label' => 'temporary password', 'rules' => UserModel::PASSWORD_RULES],
            'password_confirm' => ['label' => 'password confirmation', 'rules' => 'required|matches[password]'],
        ];
    }
}
