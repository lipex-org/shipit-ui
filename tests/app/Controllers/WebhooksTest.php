<?php

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use ShipIt\ShipIt;

/**
 * @internal
 */
final class WebhooksTest extends CIUnitTestCase
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
        $this->projectPath = sys_get_temp_dir() . '/shipit_webhook_test_proj_' . uniqid();
        $this->shipitHome = sys_get_temp_dir() . '/shipit_webhook_test_home_' . uniqid();
        
        mkdir($this->projectPath, 0777, true);
        mkdir($this->shipitHome, 0777, true);
        mkdir($this->projectPath . '/.deploy', 0777, true);
        mkdir($this->projectPath . '/backups', 0777, true);
        mkdir($this->shipitHome . '/.shipit', 0777, true);

        $this->globalConfigFile = $this->shipitHome . '/.shipit/config.json';

        $repoPath = realpath(__DIR__ . '/../../../../tests/_support/repo');

        // Write dummy project configuration
        $projectConfig = [
            'gitRepoUrl' => $repoPath,
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
                    'gitRepoUrl' => $repoPath,
                    'branch' => 'main',
                    'webhook_token' => 'correct_webhook_token_123'
                ]
            ]
        ];
        file_put_contents(
            $this->globalConfigFile,
            json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Create mock bin directory to avoid running actual composer and npm
        $mockBinDir = $this->shipitHome . '/mock_bin';
        mkdir($mockBinDir, 0777, true);
        file_put_contents($mockBinDir . '/npm', "#!/bin/sh\nexit 0");
        chmod($mockBinDir . '/npm', 0755);
        file_put_contents($mockBinDir . '/composer', "#!/bin/sh\nexit 0");
        chmod($mockBinDir . '/composer', 0755);
        $this->originalPath = getenv('PATH') ?: '';
        putenv("PATH=" . $mockBinDir . ":" . $this->originalPath);

        // Set environment variable
        putenv("SHIPIT_HOME=" . $this->shipitHome);
    }

    protected function tearDown(): void
    {
        // Clean up environment
        putenv("SHIPIT_HOME");
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

    public function testWebhookTriggerWithCorrectTokenAndMatchingBranch(): void
    {
        // GitHub-like JSON payload
        $payload = [
            'ref' => 'refs/heads/main'
        ];

        $result = $this->withBody(json_encode($payload))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/webhook/correct_webhook_token_123');

        $this->assertSame(202, $result->response()->getStatusCode());
        $responseBody = json_decode($result->getJSON(), true);
        
        $this->assertSame('started', $responseBody['status'] ?? null);
        $this->assertNotEmpty($responseBody['log_id'] ?? null);

        $logId = $responseBody['log_id'];
        $logFilePath = WRITEPATH . 'logs/' . $logId . '.log';

        // Wait up to 15 seconds for process to finish
        $attempts = 0;
        $finished = false;
        while ($attempts < 150) {
            if (file_exists($logFilePath)) {
                $content = file_get_contents($logFilePath);
                if (str_contains($content, '[FINISHED]')) {
                    $finished = true;
                    break;
                }
            }
            usleep(100000); // 100ms
            $attempts++;
        }

        $this->assertTrue($finished, "Log did not write completion marker. Content: " . (file_exists($logFilePath) ? file_get_contents($logFilePath) : 'File not found'));
        @unlink($logFilePath);
    }

    public function testWebhookTriggerWithCorrectTokenButMismatchedBranch(): void
    {
        $payload = [
            'ref' => 'refs/heads/other-branch'
        ];

        $result = $this->withBody(json_encode($payload))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/webhook/correct_webhook_token_123');

        $this->assertSame(202, $result->response()->getStatusCode());
        $responseBody = json_decode($result->getJSON(), true);

        $this->assertSame('skipped', $responseBody['status'] ?? null);
        $this->assertSame('branch mismatch', $responseBody['reason'] ?? null);

        // Verify no log file is created (and no process spawned)
        $logFiles = glob(WRITEPATH . 'logs/webhook_*.log');
        $this->assertEmpty($logFiles, "A webhook log was created for a mismatched branch.");
    }

    public function testWebhookTriggerWithIncorrectToken(): void
    {
        $payload = [
            'ref' => 'refs/heads/main'
        ];

        $result = $this->withBody(json_encode($payload))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/webhook/wrong_token_456');

        $statusCode = $result->response()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [404, 401]));
        $responseBody = json_decode($result->getJSON(), true);

        $this->assertSame('error', $responseBody['status'] ?? null);
        $this->assertSame('Invalid webhook token', $responseBody['message'] ?? null);
    }

    public function testWebhookTriggerWithPingEvent(): void
    {
        $payload = [
            'zen' => 'Non-blocking is better than blocking.',
            'hook_id' => 123456
        ];

        $result = $this->withBody(json_encode($payload))
            ->withHeaders(['Content-Type' => 'application/json', 'X-GitHub-Event' => 'ping'])
            ->post('api/webhook/correct_webhook_token_123');

        $statusCode = $result->response()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 202]));
        $responseBody = json_decode($result->getJSON(), true);

        $this->assertSame('ignored', $responseBody['status'] ?? null);
        $this->assertSame('branch mismatch or non-push event', $responseBody['reason'] ?? null);

        $logFiles = glob(WRITEPATH . 'logs/webhook_*.log');
        $this->assertEmpty($logFiles);
    }

    public function testWebhookTriggerWithNoBranchInfo(): void
    {
        $payload = [];

        $result = $this->withBody(json_encode($payload))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/webhook/correct_webhook_token_123');

        $this->assertSame(202, $result->response()->getStatusCode());
        $responseBody = json_decode($result->getJSON(), true);

        $this->assertSame('started', $responseBody['status'] ?? null);
        $this->assertNotEmpty($responseBody['log_id'] ?? null);

        $logId = $responseBody['log_id'];
        $logFilePath = WRITEPATH . 'logs/' . $logId . '.log';
        $this->assertTrue(file_exists($logFilePath), "Log file should be created for no branch info.");
        @unlink($logFilePath);
    }
}
