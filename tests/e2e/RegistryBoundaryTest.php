<?php

declare(strict_types=1);

namespace Tests\e2e;

class RegistryBoundaryTest extends ShipItE2ETestCase
{
    private string $tempWorkspace;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = getcwd();
        $this->tempWorkspace = sys_get_temp_dir() . '/shipit_e2e_reg_bound_' . uniqid();
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

    public function testMalformedJsonConfig(): void
    {
        chdir($this->tempWorkspace);
        
        // Write invalid JSON to config.json
        $deployDir = $this->tempWorkspace . '/.deploy';
        mkdir($deployDir, 0755, true);
        file_put_contents($deployDir . '/config.json', "{ malformed_json: ");

        // Run bin/shipit deploy
        $result = $this->runCliCommand(['deploy']);
        
        // It should fail gracefully, not crash with PHP fatal/warning
        $this->assertNotSame(0, $result['exit_code']);
        $this->assertStringNotContainsString('Fatal error', $result['stderr']);
        $this->assertStringNotContainsString('Warning:', $result['stderr']);
    }

    public function testShellInjectionInInit(): void
    {
        chdir($this->tempWorkspace);
        
        // Try to pass malicious gitRepoUrl containing shell metacharacters
        $injectionFile = $this->tempWorkspace . '/shell_injected_file';
        $maliciousRepo = 'git@github.com:myorg/myrepo.git; touch ' . escapeshellarg($injectionFile);

        // Run deploy command with malicious repo via argument
        $result = $this->runCliCommand(['deploy', '--repo=' . $maliciousRepo]);

        // It must not execute the command injection, so the file should not exist
        $this->assertFileDoesNotExist($injectionFile);
        $this->assertNotSame(0, $result['exit_code']);
    }

    public function testDirectoryTraversalInPath(): void
    {
        // Create directory structure with traversals
        $nestedPath = $this->tempWorkspace . '/foo/../bar';
        mkdir($this->tempWorkspace . '/foo', 0755, true);
        mkdir($this->tempWorkspace . '/bar', 0755, true);
        
        chdir($nestedPath);

        // Run bin/shipit init (answer 'n' for skeletons)
        $result = $this->runCliCommand(['init'], "n\nn\n");
        $this->assertSame(0, $result['exit_code']);

        // Verify that path registered in global config is normalized
        $shipitHome = getenv('SHIPIT_HOME');
        $globalConfigPath = $shipitHome . '/.shipit/config.json';
        $this->assertFileExists($globalConfigPath);

        $globalConfig = json_decode(file_get_contents($globalConfigPath), true);
        $projects = $globalConfig['projects'] ?? [];

        // It should be normalized to the realpath
        $realPath = realpath($this->tempWorkspace . '/bar');
        
        $this->assertArrayHasKey($realPath, $projects);
        $this->assertStringNotContainsString('..', $projects[$realPath]['path']);
    }

    public function testExtremelyLongBranchName(): void
    {
        chdir($this->tempWorkspace);
        
        $longBranch = str_repeat('a', 300);

        // Initialize
        $result = $this->runCliCommand(['init'], "n\nn\n");
        $this->assertSame(0, $result['exit_code']);

        $projectConfigPath = $this->tempWorkspace . '/.deploy/config.json';
        $this->assertFileExists($projectConfigPath);
        
        // Manually set long branch
        $config = json_decode(file_get_contents($projectConfigPath), true);
        $config['branch'] = $longBranch;
        file_put_contents($projectConfigPath, json_encode($config));

        // Verify the config file is updated correctly
        $config = json_decode(file_get_contents($projectConfigPath), true);
        $this->assertSame($longBranch, $config['branch'] ?? null);
    }

    public function testEmptyConfigValues(): void
    {
        chdir($this->tempWorkspace);

        // Run init first
        $this->runCliCommand(['init'], "n\nn\n");

        // Write config with missing critical values
        $projectConfigPath = $this->tempWorkspace . '/.deploy/config.json';
        $config = [
            'gitRepoUrl' => '',
            'branch' => '',
        ];
        file_put_contents($projectConfigPath, json_encode($config));

        // Deploy should fail gracefully
        $result = $this->runCliCommand(['deploy']);
        $this->assertNotSame(0, $result['exit_code']);
    }
}
