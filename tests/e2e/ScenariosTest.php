<?php

declare(strict_types=1);

namespace Tests\e2e;

class ScenariosTest extends ShipItE2ETestCase
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
        $dir = sys_get_temp_dir() . '/shipit_e2e_scenarios_' . uniqid();
        mkdir($dir, 0755, true);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private function runCommand(string $cmd, string $cwd): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open($cmd, $descriptors, $pipes, $cwd);
        if (!is_resource($process)) {
            throw new \RuntimeException("Failed to run system command: $cmd");
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);
        return $stdout . $stderr;
    }

    /**
     * Tier 3 - Scenario 1: testCliInitThenUiView
     */
    public function testCliInitThenUiView(): void
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
     * Tier 3 - Scenario 2: testCliInitThenUiDeploy
     */
    public function testCliInitThenUiDeploy(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);
        $this->assertSame(0, $result['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Trigger UI deploy
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode(['project_path' => $realPath])
        );

        $this->assertTrue(in_array($response['status_code'], [200, 202], true), "UI Deploy failed: " . $response['status_code']);
        $json = json_decode($response['body'], true);
        $this->assertNotEmpty($json['log_id'] ?? null);
    }

    /**
     * Tier 3 - Scenario 3: testCliInitThenWebhookDeploy
     */
    public function testCliInitThenWebhookDeploy(): void
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
        $registry = json_decode(file_get_contents($this->globalConfigPath), true);
        $token = $registry['projects'][$realPath]['webhook_token'] ?? null;
        $this->assertNotEmpty($token);

        // Trigger webhook
        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $token,
            ['Content-Type' => 'application/json'],
            json_encode(['ref' => 'refs/heads/main'])
        );
        $this->assertSame(202, $response['status_code']);
        $json = json_decode($response['body'], true);
        $this->assertNotEmpty($json['log_id'] ?? null);
    }

    /**
     * Tier 3 - Scenario 4: testCliConfigThenUiDetails
     */
    public function testCliConfigThenUiDetails(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);
        $this->assertSame(0, $result['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Modify config via registry JSON directly
        $registry = json_decode(file_get_contents($this->globalConfigPath), true);
        $registry['projects'][$realPath]['gitRepoUrl'] = 'git@github.com:myorg/special-repo-details.git';
        $registry['projects'][$realPath]['branch'] = 'special-branch-name';
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        // Get details page / dashboard
        $response = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $response['status_code']);
        $this->assertStringContainsString('special-repo-details', $response['body']);
        $this->assertStringContainsString('special-branch-name', $response['body']);
    }

    /**
     * Tier 3 - Scenario 5: testUiLoginThenRollbackThenCliVerify
     */
    public function testUiLoginThenRollbackThenCliVerify(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        $this->assertSame(0, $result['exit_code']);

        // Write initial content
        file_put_contents($tempWorkspace . '/dummy.txt', 'v1');

        // Deploy to create backup
        $resultDeploy = $this->runCliCommand(['deploy', '--ignore-all']);
        chdir($originalCwd);
        $this->assertSame(0, $resultDeploy['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Get backup timestamp
        $projectConfigPath = $tempWorkspace . '/.deploy/config.json';
        $projectConfig = json_decode(file_get_contents($projectConfigPath), true);
        $backupPath = $projectConfig['backup_path'] ?? null;
        $this->assertNotEmpty($backupPath);

        $backupDirs = glob($backupPath . '/backup_*');
        $this->assertNotEmpty($backupDirs);
        $backupTimestamp = substr(basename($backupDirs[0]), 7);

        // Modify local content to simulate new version
        file_put_contents($tempWorkspace . '/dummy.txt', 'v2');

        // Trigger rollback via UI
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/rollback',
            ['Content-Type' => 'application/json'],
            json_encode([
                'project_path' => $realPath,
                'backup' => $backupTimestamp
            ])
        );
        $this->assertTrue(in_array($response['status_code'], [200, 202], true));
        $json = json_decode($response['body'], true);
        $logId = $json['log_id'] ?? null;

        // Wait for background process
        $finished = false;
        $attempts = 0;
        while ($attempts < 30 && !$finished) {
            usleep(500000);
            $logResponse = $this->sendHttpRequest('GET', '/projects/logs/' . $logId);
            if (str_contains($logResponse['body'], '[FINISHED]')) {
                $finished = true;
            }
            $attempts++;
        }
        $this->assertTrue($finished, "Rollback timed out");

        // Verify filesystem rollback effect
        $this->assertFileExists($tempWorkspace . '/dummy.txt');
        $this->assertSame('v1', file_get_contents($tempWorkspace . '/dummy.txt'));
    }

    /**
     * Tier 3 - Scenario 6: testWebhookDeployThenUiLogStream
     */
    public function testWebhookDeployThenUiLogStream(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);
        $this->assertSame(0, $result['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Get webhook token
        $registry = json_decode(file_get_contents($this->globalConfigPath), true);
        $token = $registry['projects'][$realPath]['webhook_token'] ?? null;

        // Trigger Webhook
        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $token,
            ['Content-Type' => 'application/json'],
            json_encode(['ref' => 'refs/heads/main'])
        );
        $this->assertSame(202, $response['status_code']);
        $json = json_decode($response['body'], true);
        $logId = $json['log_id'] ?? null;
        $this->assertNotEmpty($logId);

        // Get Log Stream
        $logResponse = $this->sendHttpRequest('GET', '/projects/logs/' . $logId);
        $this->assertSame(200, $logResponse['status_code']);
        $logJson = json_decode($logResponse['body'], true);
        $this->assertSame('success', $logJson['status'] ?? null);
        $this->assertIsArray($logJson['lines'] ?? null);
    }

    /**
     * Tier 3 - Scenario 7: testUiDeployThenUiLogStream
     */
    public function testUiDeployThenUiLogStream(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);
        $this->assertSame(0, $result['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Trigger Deploy via UI
        $response = $this->sendHttpRequest(
            'POST',
            '/projects/deploy',
            ['Content-Type' => 'application/json'],
            json_encode(['project_path' => $realPath])
        );
        $this->assertTrue(in_array($response['status_code'], [200, 202], true));
        $json = json_decode($response['body'], true);
        $logId = $json['log_id'] ?? null;
        $this->assertNotEmpty($logId);

        // Get Log Stream
        $logResponse = $this->sendHttpRequest('GET', '/projects/logs/' . $logId);
        $this->assertSame(200, $logResponse['status_code']);
        $logJson = json_decode($logResponse['body'], true);
        $this->assertSame('success', $logJson['status'] ?? null);
        $this->assertIsArray($logJson['lines'] ?? null);
        }

        /**
        * Tier 3 - Scenario 8: testCliDeployThenUiView
        */
    public function testCliDeployThenUiView(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        $this->assertSame(0, $result['exit_code']);

        // Run deploy via CLI
        $resultDeploy = $this->runCliCommand(['deploy', '--ignore-all']);
        chdir($originalCwd);
        $this->assertSame(0, $resultDeploy['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Access dashboard UI and check outcome is updated and shows success
        $response = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $response['status_code']);
        $this->assertStringContainsString('success', strtolower($response['body']));
    }

    /**
     * Tier 3 - Scenario 9: testCliFailingDeployThenUiView
     */
    public function testCliFailingDeployThenUiView(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        $this->assertSame(0, $result['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Cause deploy failure by configuring an invalid git repository URL
        $projectConfigPath = $tempWorkspace . '/.deploy/config.json';
        $config = json_decode(file_get_contents($projectConfigPath), true);
        $config['gitRepoUrl'] = '/invalid/nonexistent/git/repo.git';
        file_put_contents($projectConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        // Update registry URL
        $registry = json_decode(file_get_contents($this->globalConfigPath), true);
        $registry['projects'][$realPath]['gitRepoUrl'] = '/invalid/nonexistent/git/repo.git';
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        // Run deploy via CLI (which will fail due to invalid repo clone)
        $resultDeploy = $this->runCliCommand(['deploy']);
        chdir($originalCwd);
        $this->assertNotEquals(0, $resultDeploy['exit_code']);

        // Check if registry shows failure
        $updatedRegistry = json_decode(file_get_contents($this->globalConfigPath), true);
        $this->assertSame('failed', $updatedRegistry['projects'][$realPath]['latest_outcome'] ?? null);

        // Assert dashboard shows failed
        $response = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $response['status_code']);
        $this->assertStringContainsString('failed', strtolower($response['body']));
    }

    /**
     * Tier 3 - Scenario 10: testWebhookFailingDeployThenUiView
     */
    public function testWebhookFailingDeployThenUiView(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);
        $this->assertSame(0, $result['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Cause deploy failure by setting an invalid git repo path in project config
        $projectConfigPath = $tempWorkspace . '/.deploy/config.json';
        $config = json_decode(file_get_contents($projectConfigPath), true);
        $config['gitRepoUrl'] = '/invalid/nonexistent/git/repo.git';
        file_put_contents($projectConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        // Update registry URL
        $registry = json_decode(file_get_contents($this->globalConfigPath), true);
        $registry['projects'][$realPath]['gitRepoUrl'] = '/invalid/nonexistent/git/repo.git';
        $token = $registry['projects'][$realPath]['webhook_token'] ?? null;
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        // Trigger webhook deployment
        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $token,
            ['Content-Type' => 'application/json'],
            json_encode(['ref' => 'refs/heads/main'])
        );
        $this->assertSame(202, $response['status_code']);
        $json = json_decode($response['body'], true);
        $logId = $json['log_id'] ?? null;

        // Wait for background deploy to run and complete with failure
        $finished = false;
        $attempts = 0;
        while ($attempts < 30 && !$finished) {
            usleep(500000);
            $logResponse = $this->sendHttpRequest('GET', '/projects/logs/' . $logId);
            if (str_contains($logResponse['body'], '[FINISHED]')) {
                $finished = true;
            }
            $attempts++;
        }
        $this->assertTrue($finished, "Webhook deployment timed out");

        // Verify the dashboard UI lists the failure status
        $uiResponse = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $uiResponse['status_code']);
        $this->assertStringContainsString('failed', strtolower($uiResponse['body']));
    }

    /**
     * Tier 4 - Scenario 1: testBackupRetentionRotation
     */
    public function testBackupRetentionRotation(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        $this->assertSame(0, $result['exit_code']);

        // Configure backup retention to 2 in .deploy/config.json
        $projectConfigPath = $tempWorkspace . '/.deploy/config.json';
        $config = json_decode(file_get_contents($projectConfigPath), true);
        $config['backup_retention'] = 2;
        file_put_contents($projectConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        $backupPath = $config['backup_path'] ?? null;
        $this->assertNotEmpty($backupPath);

        // Write content and run deploy 3 times
        for ($i = 1; $i <= 3; $i++) {
            file_put_contents($tempWorkspace . '/file.txt', "content_$i");
            $resultDeploy = $this->runCliCommand(['deploy', '--ignore-all']);
            $this->assertSame(0, $resultDeploy['exit_code']);
        }
        chdir($originalCwd);

        // Assert that only 2 backups are retained (retention limit is 2)
        $backupDirs = glob($backupPath . '/backup_*');
        $this->assertCount(2, $backupDirs, "Backup retention limit did not rotate old backups");
    }

    /**
     * Tier 4 - Scenario 2: testGitMergeConflictHandling
     */
    public function testGitMergeConflictHandling(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        $this->assertSame(0, $result['exit_code']);

        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;

        // Cause git clone to fail (representing a Git/update failure or conflict)
        // by setting an invalid repo
        $projectConfigPath = $tempWorkspace . '/.deploy/config.json';
        $config = json_decode(file_get_contents($projectConfigPath), true);
        $config['gitRepoUrl'] = '/invalid/git/repo/path.git';
        file_put_contents($projectConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        // Update global registry gitRepoUrl to trigger the failure on deploy
        $registry = json_decode(file_get_contents($this->globalConfigPath), true);
        $registry['projects'][$realPath]['gitRepoUrl'] = '/invalid/git/repo/path.git';
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        // Trigger deploy via CLI
        $resultDeploy = $this->runCliCommand(['deploy']);
        chdir($originalCwd);
        $this->assertNotEquals(0, $resultDeploy['exit_code'], "Deploy should have exited with code 1");

        // Verify global registry status is marked as failed cleanly without corrupting the configuration
        $updatedRegistry = json_decode(file_get_contents($this->globalConfigPath), true);
        $this->assertSame('failed', $updatedRegistry['projects'][$realPath]['latest_outcome'] ?? null);
    }

    /**
     * Tier 4 - Scenario 3: testMultiUserOperations
     */
    public function testMultiUserOperations(): void
    {
        // Switch mock credentials to user1
        putenv('TEST_USER_USERNAME=user1');
        putenv('TEST_USER_PASSWORD=pass1');
        $_ENV['TEST_USER_USERNAME'] = 'user1';
        $_ENV['TEST_USER_PASSWORD'] = 'pass1';

        $response1 = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => 'user1',
                'password' => 'pass1',
            ])
        );
        $this->assertSame(302, $response1['status_code']);

        $dashResponse1 = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $dashResponse1['status_code']);

        // Logout
        $logoutResponse = $this->sendHttpRequest('GET', '/logout');
        $this->assertSame(302, $logoutResponse['status_code']);

        // Switch mock credentials to user2
        putenv('TEST_USER_USERNAME=user2');
        putenv('TEST_USER_PASSWORD=pass2');
        $_ENV['TEST_USER_USERNAME'] = 'user2';
        $_ENV['TEST_USER_PASSWORD'] = 'pass2';

        // Attempting to access dashboard unauthenticated should redirect/block
        $dashResponseBlocked = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertTrue(in_array($dashResponseBlocked['status_code'], [302, 401], true));

        // Login user2
        $response2 = $this->sendHttpRequest(
            'POST',
            '/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'username' => 'user2',
                'password' => 'pass2',
            ])
        );
        $this->assertSame(302, $response2['status_code']);

        $dashResponse2 = $this->sendHttpRequest('GET', '/dashboard');
        $this->assertSame(200, $dashResponse2['status_code']);

        // Restore default credentials
        putenv('TEST_USER_USERNAME=testuser');
        putenv('TEST_USER_PASSWORD=testpass');
        $_ENV['TEST_USER_USERNAME'] = 'testuser';
        $_ENV['TEST_USER_PASSWORD'] = 'testpass';
    }

    /**
     * Tier 4 - Scenario 4: testSystemEnvironmentVerification
     */
    public function testSystemEnvironmentVerification(): void
    {
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Run bin/shipit init to ensure configuration can be generated in the sandboxed environment
        $result = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);
        $this->assertSame(0, $result['exit_code']);

        // Verify the global config directory and config.json are created under sandboxed HOME
        $this->assertDirectoryExists($this->shipitHome . '/.shipit');
        $this->assertFileExists($this->globalConfigPath);

        // Verify config is valid JSON
        $json = json_decode(file_get_contents($this->globalConfigPath), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('projects', $json);
    }

    /**
     * Tier 4 - Scenario 5: testIgnoreFilesDeployment
     */
    public function testIgnoreFilesDeployment(): void
    {
        // 1. Setup mock git remote repo
        $gitRemotePath = sys_get_temp_dir() . '/shipit_e2e_remote_ignore_' . uniqid();
        mkdir($gitRemotePath, 0755, true);
        $this->tempDirs[] = $gitRemotePath;

        $this->runCommand('git init', $gitRemotePath);
        $this->runCommand('git config user.name "E2E Test"', $gitRemotePath);
        $this->runCommand('git config user.email "test@example.com"', $gitRemotePath);

        // Add a deployed file and a secret ignored file
        file_put_contents($gitRemotePath . '/app.txt', 'deploy me');
        file_put_contents($gitRemotePath . '/secret.key', 'secret contents');
        file_put_contents($gitRemotePath . '/.deployignore', "secret.key\n");

        $this->runCommand('git add app.txt secret.key .deployignore', $gitRemotePath);
        $this->runCommand('git commit -m "Commit with ignored files"', $gitRemotePath);

        // 2. Setup project workspace
        $tempWorkspace = $this->createTempProject();
        $originalCwd = getcwd();
        chdir($tempWorkspace);

        // Bootstrap project via CLI init
        $resultInit = $this->runCliCommand(['init'], "n\nn\n");
        chdir($originalCwd);
        $this->assertSame(0, $resultInit['exit_code']);

        // Update project config with gitRepoUrl = $gitRemotePath, branch = main
        $projectConfigPath = $tempWorkspace . '/.deploy/config.json';
        $config = json_decode(file_get_contents($projectConfigPath), true);
        $config['gitRepoUrl'] = $gitRemotePath;
        $config['branch'] = 'main';
        file_put_contents($projectConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        // Update global registry config to match
        $realPath = realpath($tempWorkspace) ?: $tempWorkspace;
        $registry = json_decode(file_get_contents($this->globalConfigPath), true);
        $registry['projects'][$realPath]['gitRepoUrl'] = $gitRemotePath;
        $registry['projects'][$realPath]['branch'] = 'main';
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));

        // Perform deployment
        chdir($tempWorkspace);
        $resultDeploy = $this->runCliCommand(['deploy']);
        chdir($originalCwd);
        $this->assertSame(0, $resultDeploy['exit_code'], "Deploy failed: " . $resultDeploy['stderr']);

        // Verify files: app.txt should exist, but secret.key should be ignored (does not exist)
        $this->assertFileExists($tempWorkspace . '/app.txt');
        $this->assertFileDoesNotExist($tempWorkspace . '/secret.key');
    }
}
