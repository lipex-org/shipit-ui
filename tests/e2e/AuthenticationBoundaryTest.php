<?php

declare(strict_types=1);

namespace Tests\e2e;

class AuthenticationBoundaryTest extends ShipItE2ETestCase
{
    public function testExtremelyLongCredentials(): void
    {
        // Attempt login with a 10,000 character username/password
        $longUsername = str_repeat('a', 10000);
        $longPassword = str_repeat('b', 10000);

        $response = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => $longUsername,
                'password' => $longPassword,
            ])
        );

        // It must reject the login gracefully (redirect to login or return 401/400)
        $this->assertTrue(
            in_array($response['status_code'], [400, 401, 302], true),
            "Expected status 400, 401, or 302, got " . $response['status_code']
        );
    }

    public function testSqlAndShellInjectionInLogin(): void
    {
        // SQL injection payload
        $sqlPayload = "' OR '1'='1";
        // Shell injection payload
        $shellPayload = "admin; touch /tmp/should_not_exist";

        $responseSql = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => $sqlPayload,
                'password' => 'password',
            ])
        );
        $this->assertTrue(in_array($responseSql['status_code'], [401, 302], true));

        $responseShell = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => $shellPayload,
                'password' => 'password',
            ])
        );
        $this->assertTrue(in_array($responseShell['status_code'], [401, 302], true));
    }

    public function testMissingCsrfToken(): void
    {
        // First get the login page to see if a CSRF cookie is set
        $getRes = $this->sendHttpRequest('GET', '/login');
        
        $csrfActive = false;
        if (str_contains($getRes['headers'], 'csrf_cookie_name')) {
            $csrfActive = true;
        }

        // Send a POST request without a CSRF token (or with an invalid one)
        $response = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => 'testuser',
                'password' => 'testpass',
                // csrf_test_name is omitted or invalid
            ])
        );

        if ($csrfActive) {
            // If CSRF is active, it must be blocked (typically returns 403 Forbidden or redirects with error)
            $this->assertTrue(
                $response['status_code'] === 403 || 
                ($response['status_code'] === 302 && str_contains(strtolower($response['headers']), 'error')),
                "Expected CSRF block (403 or redirect with error) since CSRF cookie was set"
            );
        } else {
            // CSRF is not active in this environment, it should either redirect to dashboard (if testuser/testpass works)
            // or redirect back to login (on failure). It shouldn't crash.
            $this->assertTrue(in_array($response['status_code'], [200, 302], true));
        }
    }

    public function testMalformedSessionCookie(): void
    {
        // Clear cookies and set a corrupted cookie file content
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        
        // Write a malformed session cookie to the cookie file
        // curl cookie file format: domain, tailmatch, path, secure, expires, name, value
        $cookieData = "127.0.0.1\tFALSE\t/\tFALSE\t0\tci_session\tinvalid-corrupted-session-hash-value-123\n";
        file_put_contents($this->cookieFile, $cookieData);

        // Access a protected route
        $response = $this->sendHttpRequest('GET', '/dashboard');

        // It must redirect to login (302) or return 401
        $this->assertTrue(
            $response['status_code'] === 401 || 
            ($response['status_code'] === 302 && str_contains($response['headers'], '/login')),
            "Access was not blocked. Status: " . $response['status_code']
        );
    }

    public function testExpiredSessionCookie(): void
    {
        // Clear session cookies
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'shipit_e2e_cookie_');

        // Access dashboard
        $response = $this->sendHttpRequest('GET', '/dashboard');

        // Must redirect to login (302) or return 401
        $this->assertTrue(
            $response['status_code'] === 401 || 
            ($response['status_code'] === 302 && str_contains($response['headers'], '/login')),
            "Expired/missing session was not redirected. Status: " . $response['status_code']
        );
    }
}
