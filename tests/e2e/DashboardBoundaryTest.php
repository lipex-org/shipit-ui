<?php

declare(strict_types=1);

namespace Tests\e2e;

class DashboardBoundaryTest extends ShipItE2ETestCase
{
    private string $shipitHome;
    private string $globalConfigPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shipitHome = getenv('SHIPIT_HOME') ?: (sys_get_temp_dir() . '/shipit_home_' . uniqid());
        if (!is_dir($this->shipitHome . '/.shipit')) {
            mkdir($this->shipitHome . '/.shipit', 0755, true);
        }
        $this->globalConfigPath = $this->shipitHome . '/.shipit/config.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->globalConfigPath)) {
            @unlink($this->globalConfigPath);
        }
        if ($this->shipitHome && $this->shipitHome !== getenv('SHIPIT_HOME')) {
            $this->deleteDir($this->shipitHome);
        }
        parent::tearDown();
    }

    protected function login(): void
    {
        $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => 'testuser',
                'password' => 'testpass',
            ])
        );
    }

    public function testDashboardWithMalformedConfig(): void
    {
        $this->login();

        // Write invalid/malformed JSON to global registry config
        file_put_contents($this->globalConfigPath, "{ malformed_json: ");

        $response = $this->sendHttpRequest('GET', '/dashboard');

        // It must handle it gracefully, return 200 (or redirect to login), but not 500 crash
        $this->assertTrue(in_array($response['status_code'], [200, 302], true));
        $this->assertStringNotContainsString('Fatal error', $response['body']);
        $this->assertStringNotContainsString('Warning:', $response['body']);
    }

    public function testNonExistentProjectDetails(): void
    {
        $this->login();

        // Access/trigger actions for a non-existent project path
        // e.g. POST deploy with non-registered path
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode(['project_path' => '/nonexistent/path/to/project'])
        );

        // It should return 400 or 404
        $this->assertTrue(in_array($response['status_code'], [400, 404], true));
    }

    public function testMalformedFilterQuery(): void
    {
        $this->login();

        // Send excessively long filter query (e.g. 5000 chars)
        $longQuery = str_repeat('a', 5000);
        $response = $this->sendHttpRequest('GET', '/?search=' . $longQuery);

        // It should not crash or trigger PHP warnings
        $this->assertTrue(in_array($response['status_code'], [200, 302], true));
        $this->assertStringNotContainsString('Fatal error', $response['body']);
        $this->assertStringNotContainsString('Warning:', $response['body']);
    }

    public function testProjectWithHugeMetadata(): void
    {
        $this->login();

        // Write registry with massive project metadata
        $hugeMetadata = str_repeat('x', 500000); // 500KB
        $registry = [
            'projects' => [
                '/path/to/huge_project' => [
                    'path' => '/path/to/huge_project',
                    'gitRepoUrl' => 'git@github.com:huge/repo.git',
                    'branch' => 'main',
                    'last_shipped_at' => null,
                    'latest_outcome' => null,
                    'webhook_token' => 'token-huge',
                    'metadata' => $hugeMetadata,
                ]
            ]
        ];
        file_put_contents($this->globalConfigPath, json_encode($registry));

        $response = $this->sendHttpRequest('GET', '/dashboard');

        // It should render dashboard without memory limit exhaustion
        $this->assertSame(200, $response['status_code']);
        $this->assertStringContainsString('huge_project', $response['body']);
    }

    public function testXssInjectionInFilters(): void
    {
        $this->login();

        $xssPayload = '<script>alert("xss")</script>';
        $response = $this->sendHttpRequest('GET', '/?search=' . urlencode($xssPayload));

        // Verify that raw script tag is not present in output (must be escaped or filtered)
        $this->assertStringNotContainsString($xssPayload, $response['body']);
    }
}
