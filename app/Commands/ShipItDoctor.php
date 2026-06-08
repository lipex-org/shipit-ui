<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use ShipIt\ShipIt;

class ShipItDoctor extends BaseCommand
{
    protected $group = 'ShipIt';
    protected $name = 'shipit:doctor';
    protected $description = 'Checks if the environment is ready for the ShipIt UI to function.';

    public function run(array $params)
    {
        CLI::write('ShipIt UI Doctor - Diagnostic Tool', 'yellow');
        CLI::write('Checking system requirements and permissions...', 'cyan');
        CLI::newLine();

        $currentUser = get_current_user();
        CLI::write("Running as user: $currentUser", 'yellow');
        CLI::write("Note: For best results, run this as your web server user (e.g., www-data).", 'yellow');
        CLI::newLine();

        $allOk = true;

        // 1. Check Writable Directory
        $writablePath = WRITEPATH;
        if (is_writable($writablePath)) {
            CLI::write('[OK] Writable directory is writable: ' . $writablePath, 'green');

            // Check ownership of writable/logs
            $logsPath = $writablePath . 'logs';
            if (!is_dir($logsPath)) {
                mkdir($logsPath, 0777, true);
            }

            $owner = posix_getpwuid(fileowner($writablePath))['name'] ?? 'unknown';
            CLI::write("[INFO] Writable directory owner: $owner", 'blue');
        } else {
            CLI::error('[FAIL] Writable directory is NOT writable: ' . $writablePath);
            CLI::write('     Run: chown -R www-data:www-data ' . $writablePath, 'yellow');
            $allOk = false;
        }

        // 2. Check shipit package
        $shipit = new ShipIt();
        CLI::write("[OK] ShipIt package (v" . ShipIt::VERSION . ") is loaded via Composer.", 'green');

        $globalShipit = shell_exec('command -v shipit 2>/dev/null');
        if ($globalShipit) {
            CLI::write("[INFO] Global ShipIt command also found: " . trim($globalShipit), 'blue');
        } else {
            CLI::write('[INFO] Global "shipit" command not found (Optional, UI uses internal package).', 'blue');
        }

        // 3. Check Authentication Tools
        $hasAuth = false;
        $pwauth = $this->findTool('pwauth');
        if ($pwauth) {
            CLI::write('[OK] Found pwauth: ' . $pwauth, 'green');
            $hasAuth = true;
        }

        if (extension_loaded('ssh2')) {
            CLI::write('[OK] PHP extension ssh2 is loaded (alternative auth).', 'green');
            $hasAuth = true;
        } else {
            $sshpass = $this->findTool('sshpass');
            if ($sshpass) {
                CLI::write('[OK] Found sshpass: ' . $sshpass, 'green');
                $hasAuth = true;
            }
        }

        if (!$hasAuth) {
            CLI::error('[FAIL] No authentication tools found (pwauth, ssh2 extension, or sshpass).');
            CLI::write('     Login will likely fail.', 'yellow');
            $allOk = false;
        }

        // 4. Check Global Registry
        $shipit = new ShipIt();
        $home = $shipit->getHomeDir();
        $globalConfigDir = $home . DIRECTORY_SEPARATOR . '.shipit';
        $globalConfigFile = $globalConfigDir . DIRECTORY_SEPARATOR . 'config.json';

        if (is_dir($globalConfigDir)) {
            CLI::write('[OK] Global config directory exists: ' . $globalConfigDir, 'green');
            if (file_exists($globalConfigFile)) {
                if (is_readable($globalConfigFile)) {
                    CLI::write('[OK] Global registry is readable.', 'green');
                } else {
                    CLI::error('[FAIL] Global registry is NOT readable: ' . $globalConfigFile);
                    $allOk = false;
                }
            } else {
                CLI::write('[INFO] Global registry file does not exist yet (will be created on first init/deploy).', 'blue');
            }
        } else {
            CLI::error('[FAIL] Global config directory does NOT exist: ' . $globalConfigDir);
            CLI::write('     Run "shipit init" to set it up.', 'yellow');
            $allOk = false;
        }

        // 5. Check CLI Tools
        $tools = ['git', 'composer', 'npm', 'php'];
        foreach ($tools as $tool) {
            $path = $this->findTool($tool);
            if ($path) {
                CLI::write("[OK] Found $tool: " . $path, 'green');
            } else {
                CLI::error("[FAIL] $tool NOT found in PATH.");
                $allOk = false;
            }
        }

        CLI::newLine();
        if ($allOk) {
            CLI::write('Summary: Everything looks good!', 'green');
        } else {
            CLI::write('Summary: Some issues were found. Please fix them to ensure the UI works correctly.', 'red');
        }
    }

    private function findTool(string $tool): ?string
    {
        $paths = ['/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/local/sbin'];
        foreach ($paths as $dir) {
            $path = $dir . DIRECTORY_SEPARATOR . $tool;
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        $envPath = getenv('PATH');
        if ($envPath) {
            $dirs = explode(PATH_SEPARATOR, $envPath);
            foreach ($dirs as $dir) {
                $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $tool;
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
