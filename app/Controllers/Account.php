<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class Account extends BaseController
{
    public function password(): string
    {
        return view('account/password');
    }

    public function updatePassword(): RedirectResponse|string
    {
        $auth = service('auth');
        $user = $auth->user();

        $data = [
            'current_password' => (string) $this->request->getPost('current_password'),
            'password'         => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
        ];

        $rules = [
            'current_password' => ['label' => 'current password', 'rules' => 'required'],
            'password'         => ['label' => 'new password', 'rules' => UserModel::PASSWORD_RULES . '|differs[current_password]'],
            'password_confirm' => ['label' => 'password confirmation', 'rules' => 'required|matches[password]'],
        ];

        if (! $this->validateData($data, $rules)) {
            return view('account/password', ['errors' => $this->validator->getErrors()]);
        }

        if (! password_verify($data['current_password'], $user['password_hash'])) {
            return view('account/password', ['errors' => ['current_password' => 'Your current password is incorrect.']]);
        }

        model(UserModel::class)->update($user['id'], [
            'password_hash'        => UserModel::hashPassword($data['password']),
            'must_change_password' => false,
        ]);
        // Stay signed in here; any other session of this account is signed out.
        $auth->passwordChanged(model(UserModel::class)->find($user['id']));

        audit_log('user.password_changed', 'user', $user['id']);

        return redirect()->to(site_url('/'))->with('success', 'Your password has been changed.');
    }
}
