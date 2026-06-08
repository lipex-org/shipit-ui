<?php

declare(strict_types=1);

namespace Tests\e2e;

class RegistryTest extends ShipItE2ETestCase
{
    private string $tempWorkspace;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = getcwd();
        $this->tempWorkspace = sys_get_temp_dir() . '/shipit_e2e_registry_' . uniqid();
        mkdir($this->tempWorkspace, 0755, true);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->tempWorkspace);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testInitNewProject(): void
    {
        chdir($this->tempWorkspace);

        // Run bin/shipit init in the new workspace.
        // We simulate answering 'n' (or 'no') to creating custom adapter/server skeletons.
        $result = $this->runCliCommand(['init'], "n\nn\n");

        $this->assertSame(0, $result['exit_code'], "CLI init failed: " . $result['stderr']);

        // Check project-level config
        $projectConfigPath = $this->tempWorkspace . '/.deploy/config.json';
        $this->assertFileExists($projectConfigPath);
        $projectConfig = json_decode(file_get_contents($projectConfigPath), true);
        $this->assertIsArray($projectConfig);
        $this->assertSame('main', $projectConfig['branch'] ?? null);

        // Check global config
        $shipitHome = getenv('SHIPIT_HOME');
        $this->assertNotEmpty($shipitHome);
        $globalConfigPath = $shipitHome . '/.shipit/config.json';
        $this->assertFileExists($globalConfigPath);

        $globalConfig = json_decode(file_get_contents($globalConfigPath), true);
        $this->assertIsArray($globalConfig);
        $this->assertArrayHasKey('projects', $globalConfig);

        $realPath = realpath($this->tempWorkspace) ?: $this->tempWorkspace;
        $this->assertArrayHasKey($realPath, $globalConfig['projects']);
        $projectEntry = $globalConfig['projects'][$realPath];
        $this->assertSame($realPath, $projectEntry['path']);
        $this->assertNotEmpty($projectEntry['webhook_token']);
    }

    public function testInitExistingProject(): void
    {
        chdir($this->tempWorkspace);

        // Create project config with some custom settings
        $deployDir = $this->tempWorkspace . '/.deploy';
        mkdir($deployDir, 0755, true);
        $projectConfigPath = $deployDir . '/config.json';

        $customConfig = [
            'gitRepoUrl' => 'git@github.com:myorg/myrepo.git',
            'branch' => 'custom-branch',
            'adapter' => 'laravel',
            'server' => 'directadmin',
        ];
        file_put_contents($projectConfigPath, json_encode($customConfig, JSON_PRETTY_PRINT));

        // Now run init
        $result = $this->runCliCommand(['init'], "n\nn\n");
        $this->assertSame(0, $result['exit_code']);

        // Assert existing properties are preserved
        $this->assertFileExists($projectConfigPath);
        $projectConfig = json_decode(file_get_contents($projectConfigPath), true);
        $this->assertSame('git@github.com:myorg/myrepo.git', $projectConfig['gitRepoUrl']);
        $this->assertSame('custom-branch', $projectConfig['branch']);

        // Assert global registry config matches
        $shipitHome = getenv('SHIPIT_HOME');
        $globalConfigPath = $shipitHome . '/.shipit/config.json';
        $this->assertFileExists($globalConfigPath);

        $globalConfig = json_decode(file_get_contents($globalConfigPath), true);
        $realPath = realpath($this->tempWorkspace) ?: $this->tempWorkspace;
        $this->assertArrayHasKey($realPath, $globalConfig['projects']);
        $projectEntry = $globalConfig['projects'][$realPath];
        $this->assertSame('git@github.com:myorg/myrepo.git', $projectEntry['gitRepoUrl']);
        $this->assertSame('custom-branch', $projectEntry['branch']);
    }

    public function testDeployUpdatesStatus(): void
    {
        chdir($this->tempWorkspace);

        // Run init first
        $this->runCliCommand(['init'], "n\nn\n");

        // Set valid repository URL (e.g. mock or local)
        $projectConfigPath = $this->tempWorkspace . '/.deploy/config.json';
        $projectConfig = json_decode(file_get_contents($projectConfigPath), true);
        $projectConfig['gitRepoUrl'] = 'git@github.com:myorg/myrepo.git';
        file_put_contents($projectConfigPath, json_encode($projectConfig, JSON_PRETTY_PRINT));

        // Deploy with --ignore-all
        $result = $this->runCliCommand(['deploy', '--ignore-all']);
        $this->assertSame(0, $result['exit_code']);

        // Check global config is updated with success and last_shipped_at
        $shipitHome = getenv('SHIPIT_HOME');
        $globalConfigPath = $shipitHome . '/.shipit/config.json';
        $globalConfig = json_decode(file_get_contents($globalConfigPath), true);
        $realPath = realpath($this->tempWorkspace) ?: $this->tempWorkspace;
        $projectEntry = $globalConfig['projects'][$realPath];

        $this->assertSame('success', $projectEntry['latest_outcome']);
        $this->assertNotEmpty($projectEntry['last_shipped_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $projectEntry['last_shipped_at']);
    }

    public function testConfigCLIUpdate(): void
    {
        chdir($this->tempWorkspace);

        // Run config command on global settings
        $result = $this->runCliCommand(['config', 'foo', 'bar', '--global']);
        $this->assertSame(0, $result['exit_code']);

        // Check global config file contains the setting
        $shipitHome = getenv('SHIPIT_HOME');
        $globalConfigPath = $shipitHome . '/.shipit/config.json';
        $this->assertFileExists($globalConfigPath);

        $globalConfig = json_decode(file_get_contents($globalConfigPath), true);
        $this->assertSame('bar', $globalConfig['foo'] ?? null);
    }

    public function testFailingDeployUpdatesStatus(): void
    {
        chdir($this->tempWorkspace);

        // Run init first
        $this->runCliCommand(['init'], "n\nn\n");

        // Set invalid repository URL so git clone fails
        $projectConfigPath = $this->tempWorkspace . '/.deploy/config.json';
        $projectConfig = json_decode(file_get_contents($projectConfigPath), true);
        $projectConfig['gitRepoUrl'] = 'git@github.com:nonexistent-org-xyz/nonexistent-repo-123.git';
        file_put_contents($projectConfigPath, json_encode($projectConfig, JSON_PRETTY_PRINT));

        // Deploy (without --ignore-all so it actually tries to clone)
        $result = $this->runCliCommand(['deploy']);
        $this->assertNotSame(0, $result['exit_code']);

        // Check global config is updated with 'failed'
        $shipitHome = getenv('SHIPIT_HOME');
        $globalConfigPath = $shipitHome . '/.shipit/config.json';
        $globalConfig = json_decode(file_get_contents($globalConfigPath), true);
        $realPath = realpath($this->tempWorkspace) ?: $this->tempWorkspace;
        $projectEntry = $globalConfig['projects'][$realPath];

        $this->assertSame('failed', $projectEntry['latest_outcome']);
    }
}
