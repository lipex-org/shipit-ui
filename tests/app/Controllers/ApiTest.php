<?php

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use ShipIt\ShipIt;

/**
 * @internal
 */
final class ApiTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private string $projectPath;
    private string $shipitHome;
    private string $globalConfigFile;
    private string $originalPath;

    protected function setUp(): void
    {
        parent::setUp();
        Factories::reset();

        // Clean up any existing logs from other tests
        foreach (glob(WRITEPATH . 'logs/*.log') as $file) {
            @unlink($file);
        }

        // Setup temporary directories
        $this->projectPath = sys_get_temp_dir() . '/shipit_test_webhook_proj_' . uniqid();
        $this->shipitHome = sys_get_temp_dir() . '/shipit_test_webhook_home_' . uniqid();
        
        mkdir($this->projectPath, 0777, true);
        mkdir($this->shipitHome, 0777, true);
        mkdir($this->projectPath . '/.deploy', 0777, true);
        mkdir($this->shipitHome . '/.shipit', 0777, true);

        $this->globalConfigFile = $this->shipitHome . '/.shipit/config.json';

        // Write dummy project configuration
        $projectConfig = [
            'gitRepoUrl' => 'git@github.com:example/repo.git',
            'branch' => 'main',
            'backup_path' => $this->projectPath . '/backups'
        ];
        file_put_contents(
            $this->projectPath . '/.deploy/config.json',
            json_encode($projectConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Write dummy global registry
        $registry = [
            'projects' => [
                $this->projectPath => [
                    'path' => $this->projectPath,
                    'gitRepoUrl' => 'git@github.com:example/repo.git',
                    'branch' => 'main',
                    'webhook_token' => 'test_webhook_token_123'
                ]
            ]
        ];
        file_put_contents(
            $this->globalConfigFile,
            json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Create mock bin directory to avoid running actual deployment actions
        $mockBinDir = $this->shipitHome . '/mock_bin';
        mkdir($mockBinDir, 0777, true);
        file_put_contents($mockBinDir . '/shipit', "#!/bin/sh\nexit 0");
        chmod($mockBinDir . '/shipit', 0755);
        $this->originalPath = getenv('PATH') ?: '';
        putenv("PATH=" . $mockBinDir . ":" . $this->originalPath);

        // Set environment variables
        putenv("SHIPIT_HOME=" . $this->shipitHome);
        putenv("SHIPIT_RUNNER=true");
    }

    protected function tearDown(): void
    {
        // Clean up environment
        putenv("SHIPIT_HOME");
        putenv("SHIPIT_RUNNER");
        if (isset($this->originalPath)) {
            putenv("PATH=" . $this->originalPath);
        }

        $this->removeFolder($this->projectPath);
        $this->removeFolder($this->shipitHome);

        foreach (glob(WRITEPATH . 'logs/*.log') as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function removeFolder(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeFolder($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testWebhookEndpointIsPubliclyAccessible(): void
    {
        // No session details provided, so it checks that the endpoint bypasses AuthFilter
        $result = $this->post('api/webhook/invalid_token', []);
        
        $this->assertNotEquals(302, $result->response()->getStatusCode());
        $this->assertSame(404, $result->response()->getStatusCode());
    }

    public function testWebhookCallWithInvalidTokenReturns404(): void
    {
        $result = $this->post('api/webhook/wrong_token', []);
        $this->assertSame(404, $result->response()->getStatusCode());
        $responseBody = json_decode($result->getJSON(), true);
        $this->assertSame('error', $responseBody['status'] ?? null);
    }

    public function testWebhookCallWithValidTokenAndMatchingBranchReturns202AndTriggersDeploy(): void
    {
        $payload = [
            'ref' => 'refs/heads/main'
        ];
        
        $result = $this->withBody(json_encode($payload))
                       ->post('api/webhook/test_webhook_token_123');

        $this->assertSame(202, $result->response()->getStatusCode());
        $responseBody = json_decode($result->getJSON(), true);
        
        $this->assertSame('started', $responseBody['status'] ?? null);
        $this->assertNotEmpty($responseBody['log_id'] ?? null);

        $logId = $responseBody['log_id'];
        $logFilePath = WRITEPATH . 'logs/' . $logId . '.log';

        $this->assertTrue(file_exists($logFilePath), "Log file should be created.");
        @unlink($logFilePath);
    }

    public function testWebhookCallWithValidTokenAndBranchMismatchReturns202AndSkipsDeploy(): void
    {
        $payload = [
            'ref' => 'refs/heads/develop'
        ];
        
        $result = $this->withBody(json_encode($payload))
                       ->post('api/webhook/test_webhook_token_123');

        $this->assertSame(202, $result->response()->getStatusCode());
        $responseBody = json_decode($result->getJSON(), true);
        
        $this->assertSame('skipped', $responseBody['status'] ?? null);
        $this->assertSame('branch mismatch', $responseBody['reason'] ?? null);
    }

    public function testWebhookCallWithValidTokenAndEmptyPayloadTriggersDeploy(): void
    {
        $result = $this->post('api/webhook/test_webhook_token_123');

        $this->assertSame(202, $result->response()->getStatusCode());
        $responseBody = json_decode($result->getJSON(), true);
        
        $this->assertSame('started', $responseBody['status'] ?? null);
        $this->assertNotEmpty($responseBody['log_id'] ?? null);

        $logId = $responseBody['log_id'];
        $logFilePath = WRITEPATH . 'logs/' . $logId . '.log';
        $this->assertTrue(file_exists($logFilePath));
        @unlink($logFilePath);
    }

    public function testWebhookCallWithPingEvent(): void
    {
        $payload = [
            'zen' => 'Non-blocking is better than blocking.',
            'hook_id' => 123456
        ];

        $result = $this->withBody(json_encode($payload))
                       ->withHeaders(['Content-Type' => 'application/json', 'X-GitHub-Event' => 'ping'])
                       ->post('api/webhook/test_webhook_token_123');

        $this->assertSame(202, $result->response()->getStatusCode());
        $responseBody = json_decode($result->getJSON(), true);

        $this->assertSame('ignored', $responseBody['status'] ?? null);
        $this->assertSame('branch mismatch or non-push event', $responseBody['reason'] ?? null);

        $logFiles = glob(WRITEPATH . 'logs/webhook_*.log');
        $this->assertEmpty($logFiles);
    }
}
