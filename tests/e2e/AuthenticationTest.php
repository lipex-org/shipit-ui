<?php

declare(strict_types=1);

namespace Tests\e2e;

class AuthenticationTest extends ShipItE2ETestCase
{
    public function testLoginFormSetsCookie(): void
    {
        $username = 'testuser';
        $password = 'testpass';

        $response = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => $username,
                'password' => $password,
            ])
        );

        $this->assertTrue(in_array($response['status_code'], [200, 302], true), "Login response status must be 200 or 302");
        
        // Assert cookie file has been written with a session cookie (usually ci_session)
        $this->assertFileExists($this->cookieFile);
        $cookieContent = file_get_contents($this->cookieFile);
        $this->assertStringContainsString('ci_session', $cookieContent, "Session cookie was not set on login");

        // Verify that after login, a GET request to a protected dashboard route actually returns 200
        $dashboardResponse = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $dashboardResponse['status_code'], "Dashboard access should be allowed (HTTP 200) after successful login");
    }

    public function testBlockUnauthenticated(): void
    {
        // Clear cookies to simulate unauthenticated request
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'shipit_e2e_cookie_');

        // Access dashboard index route which requires authentication
        $response = $this->sendHttpRequest('GET', '/dashboard');

        // Unauthenticated access must redirect to /login (302) or return 401 Unauthorized
        $this->assertTrue(
            $response['status_code'] === 401 || 
            ($response['status_code'] === 302 && str_contains($response['headers'], '/login')),
            "Unauthenticated request was not blocked. Status: " . $response['status_code']
        );
    }

    public function testInvalidPasswordRejected(): void
    {
        $response = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => 'testuser',
                'password' => 'wrongpassword',
            ])
        );

        // Assert redirect to login (302)
        $this->assertSame(302, $response['status_code'], "Invalid password login must redirect");

        // GET /login to inspect flash message
        $getResponse = $this->sendHttpRequest('GET', '/login');
        $this->assertSame(200, $getResponse['status_code']);
        $this->assertStringContainsString('Invalid username or password.', $getResponse['body'], "Flash error message not found in login response body");
    }

    public function testInvalidUsernameRejected(): void
    {
        $response = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => 'nonexistentuser',
                'password' => 'somepassword',
            ])
        );

        // Assert redirect to login (302)
        $this->assertSame(302, $response['status_code'], "Invalid username login must redirect");

        // GET /login to inspect flash message
        $getResponse = $this->sendHttpRequest('GET', '/login');
        $this->assertSame(200, $getResponse['status_code']);
        $this->assertStringContainsString('Invalid username or password.', $getResponse['body'], "Flash error message not found in login response body");
    }

    public function testLogoutDestroysSession(): void
    {
        // First log in
        $loginResponse = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => 'testuser',
                'password' => 'testpass',
            ])
        );
        $this->assertTrue(in_array($loginResponse['status_code'], [200, 302], true), "Login response status must be 200 or 302");

        // Verify that after login, a GET request to a protected dashboard route actually returns 200
        $dashboardResponse = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $dashboardResponse['status_code'], "Dashboard access should be allowed (HTTP 200) after successful login");

        // Send GET to /logout (routes.php has $routes->get('logout', 'Auth::logout');)
        $response = $this->sendHttpRequest('GET', '/logout');

        // Logout should redirect (302/303) and clear session/cookies
        $this->assertTrue(in_array($response['status_code'], [302, 303], true), "Logout should redirect");
        
        // Assert session cookie is destroyed/no longer valid
        $responseAfterLogout = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertTrue(
            $responseAfterLogout['status_code'] === 401 || 
            ($responseAfterLogout['status_code'] === 302 && str_contains($responseAfterLogout['headers'], '/login')),
            "Access to dashboard was still allowed after logout"
        );
    }
}
