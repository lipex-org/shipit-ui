<?php

declare(strict_types=1);

namespace Tests\e2e;

class DashboardTest extends ShipItE2ETestCase
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

    public function testDashboardListsProjects(): void
    {
        $this->login();

        $registry = [
            'projects' => [
                '/path/to/project1' => [
                    'path' => '/path/to/project1',
                    'gitRepoUrl' => 'git@github.com:user/project1.git',
                    'branch' => 'main',
                    'last_shipped_at' => null,
                    'latest_outcome' => null,
                    'webhook_token' => 'token1',
                ],
                '/path/to/project2' => [
                    'path' => '/path/to/project2',
                    'gitRepoUrl' => 'git@github.com:user/project2.git',
                    'branch' => 'develop',
                    'last_shipped_at' => '2026-06-01 12:00:00',
                    'latest_outcome' => 'success',
                    'webhook_token' => 'token2',
                ]
            ]
        ];
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        $response = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $response['status_code']);
        $this->assertStringContainsString('project1', $response['body']);
        $this->assertStringContainsString('project2', $response['body']);
    }

    public function testProjectDetailsMatch(): void
    {
        $this->login();

        $registry = [
            'projects' => [
                '/path/to/project_detail_test' => [
                    'path' => '/path/to/project_detail_test',
                    'gitRepoUrl' => 'git@github.com:detail/repo.git',
                    'branch' => 'feature-x',
                    'last_shipped_at' => '2026-06-02 15:30:45',
                    'latest_outcome' => 'success',
                    'webhook_token' => 'token-details',
                ]
            ]
        ];
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        $response = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $response['status_code']);
        
        $body = $response['body'];
        $this->assertStringContainsString('project_detail_test', $body);
        $this->assertStringContainsString('git@github.com:detail/repo.git', $body);
        $this->assertStringContainsString('feature-x', $body);
        $this->assertStringContainsString('2026-06-02 15:30:45', $body);
        $this->assertStringContainsString('Success', $body);
    }

    public function testEmptyDashboardState(): void
    {
        $this->login();

        $registry = ['projects' => []];
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        $response = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $response['status_code']);
        $this->assertStringContainsString('No projects registered.', $response['body']);
    }

    public function testDashboardFilter(): void
    {
        $this->login();

        $registry = [
            'projects' => [
                '/path/to/apple' => [
                    'path' => '/path/to/apple',
                    'gitRepoUrl' => 'git@github.com:user/apple.git',
                    'branch' => 'main',
                    'last_shipped_at' => null,
                    'latest_outcome' => null,
                    'webhook_token' => 'token-apple',
                ],
                '/path/to/banana' => [
                    'path' => '/path/to/banana',
                    'gitRepoUrl' => 'git@github.com:user/banana.git',
                    'branch' => 'main',
                    'last_shipped_at' => null,
                    'latest_outcome' => null,
                    'webhook_token' => 'token-banana',
                ]
            ]
        ];
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        // Attempting to filter/search for "apple"
        $response = $this->sendHttpRequest('GET', '/dashboard?search=apple');
        $this->assertSame(200, $response['status_code']);
        
        // This assertion should fail/error if filtering is not yet implemented (both projects will be displayed)
        $this->assertStringContainsString('apple', $response['body']);
        $this->assertStringNotContainsString('banana', $response['body'], "Dashboard list was not filtered");
    }

    public function testStaticAssetsLoad(): void
    {
        // Try requesting favicon.ico or robots.txt
        $response = $this->sendHttpRequest('GET', '/robots.txt');
        $this->assertSame(200, $response['status_code']);
        $this->assertStringContainsString('User-agent:', $response['body']);
    }
}
