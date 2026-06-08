<?php

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Libraries\SystemAuthenticator;

/**
 * @internal
 */
final class AuthTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        Factories::reset();

        $mockAuth = new class extends SystemAuthenticator {
            public function authenticate(string $username, string $password): bool {
                return $username === 'valid_user' && $password === 'correct_password';
            }
        };
        Factories::injectMock('libraries', 'App\Libraries\SystemAuthenticator', $mockAuth);
    }

    public function testGetLoginDisplaysForm(): void
    {
        $result = $this->get('login');
        $result->assertStatus(200);
        $result->assertSee('ShipIt Control Panel');
    }

    public function testPostLoginSuccessRedirectsToHome(): void
    {
        $result = $this->post('login', [
            'username' => 'valid_user',
            'password' => 'correct_password'
        ]);

        $result->assertRedirectTo('/');
        $result->assertSessionHas('logged_in', true);
        $result->assertSessionHas('username', 'valid_user');
    }

    public function testPostLoginFailureRedirectsBack(): void
    {
        $result = $this->post('login', [
            'username' => 'invalid_user',
            'password' => 'wrong_password'
        ]);

        $this->assertTrue($result->isRedirect());
        $result->assertSessionHas('error', 'Invalid username or password.');
    }

    public function testLogoutDestroysSessionAndRedirects(): void
    {
        $result = $this->withSession([
            'logged_in' => true,
            'username'  => 'valid_user'
        ])->get('logout');

        $result->assertRedirectTo('/login');
        $result->assertSessionMissing('logged_in');
    }

    public function testFilterProtectionOnDashboardRedirectsToLogin(): void
    {
        $result = $this->get('/');
        $result->assertRedirectTo('/login');
    }

    public function testFilterProtectionOnDashboardAllowsLoggedInUser(): void
    {
        $result = $this->withSession([
            'logged_in' => true,
            'username'  => 'valid_user'
        ])->get('/dashboard');

        $result->assertStatus(200);
        $result->assertSee('ShipIt Dashboard');
    }
}
