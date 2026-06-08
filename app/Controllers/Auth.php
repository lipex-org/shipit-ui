<?php

namespace App\Controllers;

use App\Libraries\SystemAuthenticator;

class Auth extends BaseController
{
    public function login()
    {
        $session = session();

        if ($session->get('logged_in')) {
            return redirect()->to('/', 302);
        }

        if ($this->request->is('post')) {
            $username = (string) $this->request->getPost('username');
            $password = (string) $this->request->getPost('password');

            $authenticator = \CodeIgniter\Config\Factories::libraries('App\Libraries\SystemAuthenticator');
            if ($authenticator->authenticate($username, $password)) {
                $session->set([
                    'logged_in' => true,
                    'username'  => $username,
                ]);
                return redirect()->to('/', 302);
            }

            return redirect()->back(302)->withInput()->with('error', 'Invalid username or password.');
        }

        return page('login');
    }

    public function logout()
    {
        $session = session();
        $session->remove(['logged_in', 'username']);
        $session->destroy();

        return redirect()->to('/login', 302);
    }
}
