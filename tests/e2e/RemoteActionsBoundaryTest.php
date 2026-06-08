<?php

declare(strict_types=1);

namespace Tests\e2e;

class RemoteActionsBoundaryTest extends ShipItE2ETestCase
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
        $this->tempProjectDir = sys_get_temp_dir() . '/shipit_e2e_remote_bound_' . uniqid();
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
                    'webhook_token' => 'token-remote-bound',
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

    public function testInvalidBackupTimestampFormat(): void
    {
        $this->login();

        $realProjPath = realpath($this->tempProjectDir) ?: $this->tempProjectDir;
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/rollback',
            ['Content-Type' => 'application/json'],
            json_encode([
                'project_path' => $realProjPath,
                'backup' => 'invalid-timestamp-format'
            ])
        );

        // Validation should reject this with HTTP 400
        $this->assertSame(400, $response['status_code']);
    }

    public function testLogTraversalAttack(): void
    {
        $this->login();

        // Attempt path traversal via log_id
        $traversalLogId = '../../etc/passwd';
        $response = $this->sendHttpRequest('GET', '/projects/logs/' . urlencode($traversalLogId));

        // It must be blocked and return 400, 403, or 404, but not 200 with passwd contents
        $this->assertTrue(in_array($response['status_code'], [400, 403, 404], true));
        $this->assertStringNotContainsString('root:x:0:0:', $response['body']);
    }

    public function testDeployNonExistentPath(): void
    {
        $this->login();

        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode(['project_path' => '/nonexistent/path/here'])
        );

        // It should return 404 or 400
        $this->assertTrue(in_array($response['status_code'], [400, 404], true));
    }

    public function testDeployInvalidGitConfig(): void
    {
        $this->login();

        // Update the project's config.json with an invalid git repository URL
        $projectConfigPath = $this->tempProjectDir . '/.deploy/config.json';
        $config = json_decode(file_get_contents($projectConfigPath), true);
        $config['gitRepoUrl'] = 'git@github.com:nonexistent-org-xyz/nonexistent-repo-123.git';
        file_put_contents($projectConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        // Update the registry entry as well
        $realProjPath = realpath($this->tempProjectDir) ?: $this->tempProjectDir;
        $registry = [
            'projects' => [
                $realProjPath => [
                    'path' => $realProjPath,
                    'gitRepoUrl' => $config['gitRepoUrl'],
                    'branch' => 'main',
                    'webhook_token' => 'token-remote-bound',
                ]
            ]
        ];
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        // Trigger deploy
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode(['project_path' => $realProjPath])
        );

        $this->assertTrue(in_array($response['status_code'], [200, 202], true));
        
        $json = json_decode($response['body'], true);
        $logId = $json['log_id'] ?? 'dummy_log';

        // Wait for deploy background task to run and write log output
        $attempts = 0;
        $finished = false;
        while ($attempts < 15 && !$finished) {
            usleep(500000); // 0.5s
            $logResponse = $this->sendHttpRequest('GET', '/projects/logs/' . $logId);
            if (str_contains($logResponse['body'], '[FINISHED]')) {
                $finished = true;
            }
            $attempts++;
        }

        // Read global registry again
        $globalConfig = json_decode(file_get_contents($this->globalConfigPath), true);
        $projectEntry = $globalConfig['projects'][$realProjPath] ?? [];

        // It should have failed and recorded failure status
        $this->assertSame('failed', $projectEntry['latest_outcome'] ?? null);
    }

    public function testMalformedDeployPayload(): void
    {
        $this->login();

        // POST /projects/deploy with malformed JSON
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            "{ malformed_json: "
        );

        // It must return HTTP 400
        $this->assertSame(400, $response['status_code']);
    }
}
