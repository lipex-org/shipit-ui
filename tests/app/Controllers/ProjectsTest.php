<?php

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use ShipIt\ShipIt;

/**
 * @internal
 */
final class ProjectsTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private string $projectPath;
    private string $shipitHome;
    private string $globalConfigFile;
    private string $originalPath;

    protected function setUp(): void
    {
        Factories::reset();

        // Setup temporary directories
        $this->projectPath = sys_get_temp_dir() . '/shipit_test_proj_' . uniqid();
        $this->shipitHome = sys_get_temp_dir() . '/shipit_test_home_' . uniqid();
        
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
            'backup_path' => $this->projectPath . '/backups',
            'user' => 'test_user' // Matches the session user in tests
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
                    'webhook_token' => 'abcdef123456',
                    'user' => 'test_user'
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

        // Set environment variables
        putenv("SHIPIT_HOME=" . $this->shipitHome);
        putenv("SHIPIT_RUNNER=true");

        parent::setUp();
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

    public function testDeploySpawnsProcessAndReturnsLogId(): void
    {
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->post('projects/deploy', [
                'project_path' => $this->projectPath
            ]);

        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);

        $this->assertSame('started', $responseBody['status'] ?? null);
        $this->assertNotEmpty($responseBody['log_id'] ?? null);

        $logFilePath = WRITEPATH . 'logs/' . $responseBody['log_id'] . '.log';
        $this->assertFileExists($logFilePath);
        @unlink($logFilePath);
    }

    public function testDeployWithMalformedJsonReturns400(): void
    {
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->withBody('{ malformed_json: ')
            ->post('projects/deploy');

        $result->assertStatus(400);
        $responseBody = json_decode($result->getJSON(), true);
        $this->assertSame('error', $responseBody['status'] ?? null);
        $this->assertStringContainsString('Project path is required', $responseBody['message'] ?? '');
    }

    public function testDeployWithMissingPathReturns400(): void
    {
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->post('projects/deploy', []);

        $result->assertStatus(400);
        $responseBody = json_decode($result->getJSON(), true);
        $this->assertSame('error', $responseBody['status'] ?? null);
        $this->assertStringContainsString('Project path is required', $responseBody['message'] ?? '');
    }

    public function testRollbackSpawnsProcessAndReturnsLogId(): void
    {
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->post('projects/rollback', [
                'project_path' => $this->projectPath,
                'backup' => '20230101_120000'
            ]);

        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);

        $this->assertSame('started', $responseBody['status'] ?? null);
        $this->assertNotEmpty($responseBody['log_id'] ?? null);

        $logFilePath = WRITEPATH . 'logs/' . $responseBody['log_id'] . '.log';
        $this->assertFileExists($logFilePath);
        @unlink($logFilePath);
    }

    public function testRollbackWithMissingBackupReturns400(): void
    {
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->post('projects/rollback', [
                'project_path' => $this->projectPath
            ]);

        $result->assertStatus(400);
        $responseBody = json_decode($result->getJSON(), true);
        $this->assertSame('error', $responseBody['status'] ?? null);
        $this->assertStringContainsString('Backup timestamp is required', $responseBody['message'] ?? '');
    }

    public function testLogStreamingEndpointStreamsLogContent(): void
    {
        $logId = 'test_stream_' . uniqid();
        $logDirectory = WRITEPATH . 'logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }
        $logFilePath = $logDirectory . '/' . $logId . '.log';
        file_put_contents($logFilePath, "Step 1: Init\nStep 2: Sync\n[FINISHED]\n");

        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->get('projects/logs/' . $logId);

        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);

        $this->assertSame('success', $responseBody['status'] ?? null);
        $this->assertContains('Step 1: Init', $responseBody['lines'] ?? []);
        $this->assertContains('Step 2: Sync', $responseBody['lines'] ?? []);
        $this->assertContains('[FINISHED]', $responseBody['lines'] ?? []);
        $this->assertTrue($responseBody['finished'] ?? false);

        @unlink($logFilePath);
    }

    public function testLogsWithInvalidIdReturns400(): void
    {
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->get('projects/logs/some:invalid:id');

        $result->assertStatus(400);
        $responseBody = json_decode($result->getJSON(), true);
        $this->assertSame('error', $responseBody['status'] ?? null);
        $this->assertStringContainsString('Invalid log ID', $responseBody['message'] ?? '');
    }

    public function testValidateProjectWithQueryParam(): void
    {
        $encodedPath = base64_encode($this->projectPath);
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->get('projects/validate?path=' . urlencode($encodedPath));

        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);
        
        $this->assertSame('success', $responseBody['status'] ?? null);
        $this->assertIsArray($responseBody['results'] ?? null);
    }

    public function testRegenerateWebhookToken(): void
    {
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->post('projects/webhook/regenerate', [
                'project_path' => $this->projectPath
            ]);

        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);

        $this->assertSame('success', $responseBody['status'] ?? null);
        $this->assertNotEmpty($responseBody['new_token'] ?? null);

        // Verify registry was actually updated
        $registry = json_decode(file_get_contents($this->shipitHome . '/.shipit/config.json'), true);
        $this->assertSame($responseBody['new_token'], $registry['projects'][$this->projectPath]['webhook_token']);
    }

    public function testGetEnvContent(): void
    {
        $envPath = $this->projectPath . '/.env';
        $testContent = "DB_NAME=test\nAPI_KEY=12345";
        file_put_contents($envPath, $testContent);

        $encodedPath = base64_encode($this->projectPath);
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->get('projects/env?path=' . urlencode($encodedPath));

        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);
        $this->assertSame('success', $responseBody['status'] ?? null);
        $this->assertSame($testContent, $responseBody['content'] ?? null);

        @unlink($envPath);
    }

    public function testUpdateEnvContent(): void
    {
        $envPath = $this->projectPath . '/.env';
        $newContent = "DB_NAME=prod\nAPI_KEY=99999";

        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->post('projects/env', [
                'project_path' => $this->projectPath,
                'content' => $newContent
            ]);

        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);
        $this->assertSame('success', $responseBody['status'] ?? null);

        $this->assertFileExists($envPath);
        $this->assertSame($newContent, file_get_contents($envPath));

        @unlink($envPath);
    }

    public function testGetProjectConfig(): void
    {
        $encodedPath = base64_encode($this->projectPath);
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->get('projects/config?path=' . urlencode($encodedPath));

        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);
        $this->assertSame('success', $responseBody['status'] ?? null);
        $this->assertIsArray($responseBody['config'] ?? null);
        $this->assertSame('test_user', $responseBody['config']['user'] ?? null);
    }

    public function testUpdateProjectConfig(): void
    {
        $newBranch = 'develop';
        $result = $this->withSession(['logged_in' => true, 'username' => 'test_user'])
            ->post('projects/config', [
                'project_path' => $this->projectPath,
                'branch' => $newBranch,
                'backup_retention' => 10
            ]);

        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);
        $this->assertSame('success', $responseBody['status'] ?? null);

        // Verify file update
        $config = json_decode(file_get_contents($this->projectPath . '/.deploy/config.json'), true);
        $this->assertSame($newBranch, $config['branch']);
        $this->assertSame(10, $config['backup_retention']);

        // Verify registry update (via ShipIt class side effect)
        $registry = json_decode(file_get_contents($this->shipitHome . '/.shipit/config.json'), true);
        $this->assertSame($newBranch, $registry['projects'][$this->projectPath]['branch']);
    }
}
