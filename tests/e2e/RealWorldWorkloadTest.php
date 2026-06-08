<?php

declare(strict_types=1);

namespace Tests\e2e;

class RealWorldWorkloadTest extends ShipItE2ETestCase
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
        
        // Log in to ensure valid session cookie is active
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
        $dir = sys_get_temp_dir() . '/shipit_e2e_real_' . uniqid();
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
     * testFullWorkspaceLifecycle(): Automates a full multi-stage release lifecycle:
     * bootstrapping a project, pushing git changes, verifying webhook automation,
     * viewing real-time logs, and manual UI-triggered rollback.
     */
    public function testFullWorkspaceLifecycle(): void
    {
        // 1. Setup mock git remote repo
        $gitRemotePath = sys_get_temp_dir() . '/shipit_e2e_git_remote_' . uniqid();
        mkdir($gitRemotePath, 0755, true);
        $this->tempDirs[] = $gitRemotePath;

        $this->runCommand('git init', $gitRemotePath);
        $this->runCommand('git config user.name "E2E Test"', $gitRemotePath);
        $this->runCommand('git config user.email "test@example.com"', $gitRemotePath);
        file_put_contents($gitRemotePath . '/app.txt', 'Version 1');
        $this->runCommand('git add app.txt', $gitRemotePath);
        $this->runCommand('git commit -m "Initial commit"', $gitRemotePath);

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

        $token = $registry['projects'][$realPath]['webhook_token'] ?? null;
        $this->assertNotEmpty($token);

        // 3. Perform initial deploy to verify checkout and backup
        chdir($tempWorkspace);
        $resultDeploy1 = $this->runCliCommand(['deploy', '--ignore-all']);
        chdir($originalCwd);
        $this->assertSame(0, $resultDeploy1['exit_code'], "Initial deploy failed: " . $resultDeploy1['stderr']);

        // Verify "Version 1" is checked out
        $this->assertFileExists($tempWorkspace . '/app.txt');
        $this->assertSame('Version 1', file_get_contents($tempWorkspace . '/app.txt'));

        // Save the first backup directory name/timestamp
        $backupPath = $config['backup_path'] ?? null;
        $this->assertNotEmpty($backupPath);
        $backupDirs = glob($backupPath . '/backup_*');
        $this->assertNotEmpty($backupDirs);
        $firstBackupTimestamp = substr(basename($backupDirs[0]), 7);

        // 4. Push git changes
        file_put_contents($gitRemotePath . '/app.txt', 'Version 2');
        $this->runCommand('git add app.txt', $gitRemotePath);
        $this->runCommand('git commit -m "Update to version 2"', $gitRemotePath);

        // 5. Verify webhook automation
        $payload = [
            'ref' => 'refs/heads/main',
            'repository' => [
                'url' => $gitRemotePath
            ]
        ];
        $webhookResponse = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $token,
            ['Content-Type' => 'application/json'],
            json_encode($payload)
        );
        $this->assertSame(202, $webhookResponse['status_code']);

        $json = json_decode($webhookResponse['body'], true);
        $logId = $json['log_id'] ?? null;
        $this->assertNotEmpty($logId);

        // 6. View real-time deployment logs
        $finished = false;
        $attempts = 0;
        while ($attempts < 30 && !$finished) {
            usleep(500000); // 0.5s
            $logResponse = $this->sendHttpRequest('GET', '/projects/logs/' . $logId);
            $this->assertSame(200, $logResponse['status_code']);
            if (str_contains($logResponse['body'], '[FINISHED]')) {
                $finished = true;
            }
            $attempts++;
        }
        $this->assertTrue($finished, "Webhook deployment background process did not finish in time");

        // Verify Version 2 is deployed
        $this->assertSame('Version 2', file_get_contents($tempWorkspace . '/app.txt'));

        // 7. Perform manual UI-triggered rollback back to Version 1
        $rollbackResponse = $this->sendHttpRequest(
            'POST',
            '/projects/rollback',
            ['Content-Type' => 'application/json'],
            json_encode([
                'project_path' => $realPath,
                'backup' => $firstBackupTimestamp
            ])
        );
        $this->assertTrue(in_array($rollbackResponse['status_code'], [200, 202], true));
        $rollbackJson = json_decode($rollbackResponse['body'], true);
        $rollbackLogId = $rollbackJson['log_id'] ?? null;
        $this->assertNotEmpty($rollbackLogId);

        // Wait for rollback to finish
        $finished = false;
        $attempts = 0;
        while ($attempts < 30 && !$finished) {
            usleep(500000); // 0.5s
            $logResponse = $this->sendHttpRequest('GET', '/projects/logs/' . $rollbackLogId);
            $this->assertSame(200, $logResponse['status_code']);
            if (str_contains($logResponse['body'], '[FINISHED]')) {
                $finished = true;
            }
            $attempts++;
        }
        $this->assertTrue($finished, "Rollback background process did not finish in time");

        // Verify file is reverted to Version 1
        $this->assertSame('Version 1', file_get_contents($tempWorkspace . '/app.txt'));
    }

    /**
     * testConcurrencyAndLockStress(): Simulates concurrent webhook events and dashboard deploy triggers
     * targeting the registry and filesystems under load, verifying config locks prevent corruption.
     */
    public function testConcurrencyAndLockStress(): void
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

        // Prepare 10 concurrent requests (5 webhooks, 5 deploy actions)
        $serverUrl = getenv('TEST_SERVER_URL');
        $this->assertNotEmpty($serverUrl);

        $urls = [];
        $posts = [];
        for ($i = 0; $i < 5; $i++) {
            $urls[] = rtrim($serverUrl, '/') . '/api/webhook/' . $token;
            $posts[] = json_encode(['ref' => 'refs/heads/main']);

            $urls[] = rtrim($serverUrl, '/') . '/projects/deploy';
            $posts[] = json_encode(['project_path' => $realPath]);
        }

        $mh = curl_multi_init();
        $chs = [];

        foreach ($urls as $idx => $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $posts[$idx]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            if ($this->cookieFile) {
                curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
                curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            }
            curl_multi_add_handle($mh, $ch);
            $chs[] = $ch;
        }

        // Execute handles concurrently
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        // Close handles
        foreach ($chs as $ch) {
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        // Now assert the global config file is NOT corrupted
        $this->assertFileExists($this->globalConfigPath);
        $content = file_get_contents($this->globalConfigPath);
        
        $json = json_decode($content, true);
        $this->assertIsArray($json, "Global registry configuration was corrupted: " . $content);
        $this->assertArrayHasKey('projects', $json);
        $this->assertArrayHasKey($realPath, $json['projects']);
    }
}
