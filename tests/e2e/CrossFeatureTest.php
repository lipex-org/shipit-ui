<?php

declare(strict_types=1);

namespace Tests\e2e;

class CrossFeatureTest extends ShipItE2ETestCase
{
    private string $shipitHome;
    private string $globalConfigPath;
    private array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->shipitHome = getenv('SHIPIT_HOME') ?: (sys_get_temp_dir() . '/shipit_home_' . uniqid());
        if (!is_dir($this->shipitHome . '/.shipit')) {
            mkdir($this->shipitHome . '/.shipit', 0755, true);
        }
        $this->globalConfigPath = $this->shipitHome . '/.shipit/config.json';
        
        // Log in to ensure session cookie is created
        $this->login();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->globalConfigPath)) {
            @unlink($this->globalConfigPath);
        }
        foreach ($this->tempDirs as $dir) {
            $this->deleteDir($dir);
        }
        if ($this->shipitHome && $this->shipitHome !== getenv('SHIPIT_HOME')) {
            $this->deleteDir($this->shipitHome);
        }
        parent::tearDown();
    }

    private function login(): void
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

    private function createTempProject(): string
    {
        $dir = sys_get_temp_dir() . '/shipit_e2e_cross_' . uniqid();
        mkdir($dir, 0755, true);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    /**
     * testRegistryAndUI(): Registers a project via CLI and asserts it appears on the dashboard UI.
     */
    public function testRegistryAndUI(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init in the new workspace
        $result = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);

        $this->assertSame(0, $result['exit_code'], "CLI init failed: " . $result['stderr']);

        // Assert it appears on the dashboard UI
        $response = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $response['status_code']);
        
        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;
        $this->assertStringContainsString(basename($realPath), $response['body'], "Project did not appear on the dashboard UI");
    }

    /**
     * testAuthAndActions(): Verifies that triggering remote deploy/rollback requires a valid session.
     */
    public function testAuthAndActions(): void
    {
        $tempWorkspace = $this->createTempProject();
        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Clear cookies to simulate unauthenticated request
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'shipit_e2e_cookie_');

        // Access protected deploy endpoint
        $responseDeploy = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode(['project_path' => $realPath])
        );
        $this->assertTrue(
            $responseDeploy['status_code'] === 401 || $responseDeploy['status_code'] === 302,
            "Unauthenticated deploy request was not blocked. Status: " . $responseDeploy['status_code']
        );

        // Access protected rollback endpoint
        $responseRollback = $this->sendHttpRequest(
            'POST',
            '/projects/rollback',
            ['Content-Type' => 'application/json'],
            json_encode([
                'project_path' => $realPath,
                'backup' => '20260601_120000'
            ])
        );
        $this->assertTrue(
            $responseRollback['status_code'] === 401 || $responseRollback['status_code'] === 302,
            "Unauthenticated rollback request was not blocked. Status: " . $responseRollback['status_code']
        );
    }

    /**
     * testWebhookAndRegistryUI(): Triggering a push webhook updates project registry status & dashboard details.
     */
    public function testWebhookAndRegistryUI(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);
        $this->assertSame(0, $result['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Load global config to get the webhook token
        $this->assertFileExists($this->globalConfigPath);
        $registry = json_decode(file_get_contents($this->globalConfigPath), true);
        $token = $registry['projects'][$realPath]['webhook_token'] ?? null;
        $this->assertNotEmpty($token);

        // Trigger the push webhook
        $payload = [
            'ref' => 'refs/heads/main',
            'repository' => [
                'url' => 'git@github.com:myorg/myrepo.git'
            ]
        ];
        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $token,
            ['Content-Type' => 'application/json'],
            json_encode($payload)
        );
        $this->assertSame(202, $response['status_code']);

        // Since webhook starts background deploy, wait a brief moment for execution
        usleep(800000); // 0.8 seconds

        // Registry status and dashboard details should be updated
        $updatedRegistry = json_decode(file_get_contents($this->globalConfigPath), true);
        $projectEntry = $updatedRegistry['projects'][$realPath] ?? [];
        $this->assertNotEmpty($projectEntry['latest_outcome'] ?? null, "Webhook did not update registry outcome");

        // Verify dashboard displays the updated status
        $dashboardResponse = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $dashboardResponse['status_code']);
        $this->assertStringContainsString(basename($realPath), $dashboardResponse['body']);
        $this->assertTrue(
            str_contains(strtolower($dashboardResponse['body']), 'success') || 
            str_contains(strtolower($dashboardResponse['body']), 'failed'),
            "Dashboard did not display the outcome"
        );
    }

    /**
     * testDeployBackupRollback(): Running deploy creates a backup, which is listable and selectable via Rollback API.
     */
    public function testDeployBackupRollback(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $resultInit = $this->runCliCommand(['init'], "n\nn\n");
        $this->assertSame(0, $resultInit['exit_code']);

        // Write a dummy file to be backed up
        file_put_contents($tempWorkspace . '/dummy.txt', 'v1');

        // Run deploy via CLI with --ignore-all to create a backup
        $resultDeploy = $this->runCliCommand(['deploy', '--ignore-all']);
        chdir($originalCwd);
        $this->assertSame(0, $resultDeploy['exit_code'], "Deploy CLI command failed: " . $resultDeploy['stderr']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Verify dashboard list shows the backup
        $dashboardResponse = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $dashboardResponse['status_code']);
        
        $projectConfigPath = $tempWorkspace . '/.deploy/config.json';
        $this->assertFileExists($projectConfigPath);
        $projectConfig = json_decode(file_get_contents($projectConfigPath), true);
        $backupPath = $projectConfig['backup_path'] ?? null;
        $this->assertNotEmpty($backupPath);

        $backupDirs = glob($backupPath . '/backup_*');
        $this->assertNotEmpty($backupDirs, "No backup directory was created");
        $backupFolderName = basename($backupDirs[0]);
        $backupTimestamp = substr($backupFolderName, 7);
        $this->assertNotEmpty($backupTimestamp);

        // Assert the backup timestamp is selectable on the dashboard page
        $this->assertStringContainsString($backupTimestamp, $dashboardResponse['body'], "Backup timestamp not found on dashboard");

        // Request rollback using the rollback API
        $rollbackResponse = $this->sendHttpRequest(
            'POST',
            '/projects/rollback',
            ['Content-Type' => 'application/json'],
            json_encode([
                'project_path' => $realPath,
                'backup' => $backupTimestamp
            ])
        );

        $this->assertTrue(in_array($rollbackResponse['status_code'], [200, 202], true));
        $json = json_decode($rollbackResponse['body'], true);
        $this->assertSame('started', $json['status'] ?? null);
        $this->assertNotEmpty($json['log_id'] ?? null);
    }

    /**
     * testWebhookActionLogs(): webhook-triggered deploy generates log files streamable via endpoint.
     */
    public function testWebhookActionLogs(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);
        $this->assertSame(0, $result['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Load global config to get token
        $registry = json_decode(file_get_contents($this->globalConfigPath), true);
        $token = $registry['projects'][$realPath]['webhook_token'] ?? null;
        $this->assertNotEmpty($token);

        // Trigger push webhook
        $payload = [
            'ref' => 'refs/heads/main',
            'repository' => [
                'url' => 'git@github.com:myorg/myrepo.git'
            ]
        ];
        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $token,
            ['Content-Type' => 'application/json'],
            json_encode($payload)
        );
        $this->assertSame(202, $response['status_code']);
        
        $json = json_decode($response['body'], true);
        $this->assertIsArray($json);
        $logId = $json['log_id'] ?? null;
        $this->assertNotEmpty($logId);

        // Wait a brief moment for file generation
        usleep(500000);

        // Verify logs endpoint is readable and streaming
        $logResponse = $this->sendHttpRequest('GET', '/projects/logs/' . $logId);
        $this->assertSame(200, $logResponse['status_code']);
        $logJson = json_decode($logResponse['body'], true);
        $this->assertSame('success', $logJson['status'] ?? null);
        $this->assertIsArray($logJson['lines'] ?? null);
    }
}
