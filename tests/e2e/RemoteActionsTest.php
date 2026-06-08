<?php

declare(strict_types=1);

namespace Tests\e2e;

class RemoteActionsTest extends ShipItE2ETestCase
{
    private string $shipitHome;
    private string $globalConfigPath;
    private string $tempProjectDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shipitHome = getenv('SHIPIT_HOME') ?: (sys_get_temp_dir() . '/shipit_home_' . uniqid());
        if (!is_dir($this->shipitHome . '/.shipit')) {
            mkdir($this->shipitHome . '/.shipit', 0755, true);
        }
        $this->globalConfigPath = $this->shipitHome . '/.shipit/config.json';

        // Set up a mock project directory
        $this->tempProjectDir = sys_get_temp_dir() . '/shipit_e2e_remote_' . uniqid();
        mkdir($this->tempProjectDir, 0755, true);
        mkdir($this->tempProjectDir . '/.deploy', 0755, true);

        // Put a basic project config
        $projectConfig = [
            'gitRepoUrl' => 'git@github.com:myorg/myrepo.git',
            'branch' => 'main',
        ];
        file_put_contents($this->tempProjectDir . '/.deploy/config.json', json_encode($projectConfig, JSON_PRETTY_PRINT));

        // Register in global registry
        $realProjPath = realpath($this->tempProjectDir) ?: $this->tempProjectDir;
        $registry = [
            'projects' => [
                $realProjPath => [
                    'path' => $realProjPath,
                    'gitRepoUrl' => 'git@github.com:myorg/myrepo.git',
                    'branch' => 'main',
                    'webhook_token' => 'token-remote-123',
                ]
            ]
        ];
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->globalConfigPath)) {
            @unlink($this->globalConfigPath);
        }
        $this->deleteDir($this->tempProjectDir);
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

    public function testDeployActionReturnsLogId(): void
    {
        $this->login();

        $realProjPath = realpath($this->tempProjectDir) ?: $this->tempProjectDir;
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode(['project_path' => $realProjPath])
        );

        $this->assertTrue(in_array($response['status_code'], [200, 202], true), "Expected 200 or 202 status code");
        
        $json = json_decode($response['body'], true);
        $this->assertIsArray($json);
        $this->assertSame('started', $json['status'] ?? null);
        $this->assertNotEmpty($json['log_id'] ?? null);
    }

    public function testRollbackActionReturnsLogId(): void
    {
        $this->login();

        $realProjPath = realpath($this->tempProjectDir) ?: $this->tempProjectDir;
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/rollback',
            ['Content-Type' => 'application/json'],
            json_encode([
                'project_path' => $realProjPath,
                'backup' => '20260601_120000'
            ])
        );

        $this->assertTrue(in_array($response['status_code'], [200, 202], true), "Expected 200 or 202 status code");
        
        $json = json_decode($response['body'], true);
        $this->assertIsArray($json);
        $this->assertSame('started', $json['status'] ?? null);
        $this->assertNotEmpty($json['log_id'] ?? null);
    }

    public function testGetLogStream(): void
    {
        $this->login();

        $realProjPath = realpath($this->tempProjectDir) ?: $this->tempProjectDir;
        
        // Trigger a deploy to generate a log
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode(['project_path' => $realProjPath])
        );
        $json = json_decode($response['body'], true);
        $logId = $json['log_id'] ?? 'dummy_log_id';

        // Request the stream endpoint
        $streamResponse = $this->sendHttpRequest('GET', '/projects/logs/' . $logId);
        
        $this->assertSame(200, $streamResponse['status_code']);
        $logJson = json_decode($streamResponse['body'], true);
        $this->assertSame('success', $logJson['status'] ?? null);
        $this->assertIsArray($logJson['lines'] ?? null);
    }

    public function testDeployRunsBackground(): void
    {
        $this->login();

        $realProjPath = realpath($this->tempProjectDir) ?: $this->tempProjectDir;
        
        $startTime = microtime(true);
        
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode(['project_path' => $realProjPath])
        );
        
        $duration = microtime(true) - $startTime;
        
        // Response should return quickly (non-blocking, typical threshold < 500ms)
        $this->assertLessThan(1.0, $duration, "Deployment action was blocking, took too long: {$duration}s");
        
        $this->assertTrue(in_array($response['status_code'], [200, 202], true));
        $json = json_decode($response['body'], true);
        $this->assertNotEmpty($json['log_id']);
    }

    public function testInvalidActionPayload(): void
    {
        $this->login();

        // Call deploy without project path
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode([])
        );

        $this->assertSame(400, $response['status_code']);
        $json = json_decode($response['body'], true);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertNotEmpty($json['message'] ?? null);
    }
}
