<?php

declare(strict_types=1);

/**
 * ShipIt E2E Test Runner Harness
 *
 * Sets up an isolated sandbox, dynamically resolves a free port, starts the
 * PHP development server, runs the PHPUnit E2E test suite, and cleans up on exit.
 */

// 1. Establish isolated testing directory for HOME and SHIPIT_HOME
$tempHome = sys_get_temp_dir() . '/shipit_e2e_home_' . bin2hex(random_bytes(8));
if (!mkdir($tempHome, 0755, true)) {
    fwrite(STDERR, "Error: Failed to create temporary E2E home directory: $tempHome\n");
    exit(1);
}

// 2. Set environment variables for isolation in current process
putenv("HOME={$tempHome}");
putenv("SHIPIT_HOME={$tempHome}");
$_ENV['HOME'] = $tempHome;
$_ENV['SHIPIT_HOME'] = $tempHome;

// 3. Dynamically resolve a free TCP port using socket programming or fallback port probe
$port = 0;
if (function_exists('socket_create')) {
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket !== false) {
        if (socket_bind($socket, '127.0.0.1', 0) && socket_listen($socket) && socket_getsockname($socket, $address, $port)) {
            socket_close($socket);
        } else {
            socket_close($socket);
            $port = 0;
        }
    }
}

// Fallback if socket creation failed or was disabled
if ($port === 0) {
    // Probe ports starting from 9000 to find a free one
    for ($p = 9000; $p <= 9999; $p++) {
        $fp = @fsockopen('127.0.0.1', $p, $errno, $errstr, 0.05);
        if (!$fp) {
            $port = $p;
            break;
        } else {
            fclose($fp);
        }
    }
}

if ($port === 0) {
    fwrite(STDERR, "Error: Failed to dynamically find a free TCP port.\n");
    exit(1);
}

$serverUrl = "http://127.0.0.1:{$port}";
putenv("TEST_SERVER_URL={$serverUrl}");
$_ENV['TEST_SERVER_URL'] = $serverUrl;

$rootDir = dirname(dirname(__DIR__));

echo "==================================================\n";
echo "ShipIt E2E Test Runner Harness\n";
echo "==================================================\n";
echo "Temp Home Directory: {$tempHome}\n";
echo "Server URL:          {$serverUrl}\n";
echo "==================================================\n\n";

// 4. Ensure the ui/public directory exists
$publicDir = realpath(__DIR__ . '/../../public');
if (!$publicDir || !is_dir($publicDir)) {
    fwrite(STDERR, "Error: UI public directory not found. Tried: " . __DIR__ . '/../../public' . "\n");
    exit(1);
}

// 5. Setup PHP Server Process Pipes and Descriptors
$stdoutFile = tempnam(sys_get_temp_dir(), 'shipit_server_stdout_');
$stderrFile = tempnam(sys_get_temp_dir(), 'shipit_server_stderr_');

$descriptors = [
    0 => ['pipe', 'r'],             // stdin
    1 => ['file', $stdoutFile, 'w'], // stdout
    2 => ['file', $stderrFile, 'w'], // stderr
];

$serverCmd = "exec php -d display_errors=1 -S 127.0.0.1:{$port} -t " . escapeshellarg($publicDir);

// Setup background server process environment
$serverEnv = array_merge($_ENV, [
    'HOME' => $tempHome,
    'SHIPIT_HOME' => $tempHome,
    'TEST_SERVER_URL' => $serverUrl,
    'CI_ENVIRONMENT' => 'testing',
]);

$serverProcess = proc_open($serverCmd, $descriptors, $pipes, null, $serverEnv);
if (!is_resource($serverProcess)) {
    fwrite(STDERR, "Error: Failed to start background PHP development server.\n");
    exit(1);
}

// 6. Register Shutdown Cleanup Handler
function recursiveRmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            recursiveRmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

register_shutdown_function(function () use (&$serverProcess, $tempHome, $stdoutFile, $stderrFile) {
    echo "\nCleaning up E2E environment...\n";

    if ($serverProcess && is_resource($serverProcess)) {
        $status = proc_get_status($serverProcess);
        if ($status && $status['running']) {
            echo "Stopping background PHP web server (PID: {$status['pid']})...\n";
            proc_terminate($serverProcess, 9); // Force kill (SIGKILL)
        }
        proc_close($serverProcess);
    }

    if (file_exists($stdoutFile)) {
        @unlink($stdoutFile);
    }
    if (file_exists($stderrFile)) {
        @unlink($stderrFile);
    }

    if (is_dir($tempHome)) {
        echo "Removing temporary directory: {$tempHome}\n";
        recursiveRmdir($tempHome);
    }
    echo "Cleanup complete.\n";
});

// 7. Perform readiness check on the server
echo "Waiting for PHP development server to become responsive...\n";
$ready = false;
$maxAttempts = 50; // Wait up to 5 seconds
for ($i = 0; $i < $maxAttempts; $i++) {
    // Perform socket check
    $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
    if (is_resource($conn)) {
        fclose($conn);
        $ready = true;
        break;
    }

    // Check if server exited early
    $status = proc_get_status($serverProcess);
    if ($status && !$status['running']) {
        fwrite(STDERR, "Error: PHP development server exited prematurely.\n");
        if (file_exists($stderrFile)) {
            fwrite(STDERR, "Server stderr logs:\n" . file_get_contents($stderrFile) . "\n");
        }
        exit(1);
    }

    usleep(100000); // 100ms
}

if (!$ready) {
    fwrite(STDERR, "Error: PHP development server failed to respond on 127.0.0.1:$port within 5 seconds.\n");
    exit(1);
}

echo "PHP development server is online and verified.\n\n";

// 8. Run PHPUnit E2E tests
$phpunitBin = $rootDir . '/vendor/bin/phpunit';
if (!file_exists($phpunitBin)) {
    $phpunitBin = 'vendor/bin/phpunit';
}

$phpunitCmd = "php " . escapeshellarg($phpunitBin) . " --configuration phpunit.xml --testsuite E2E";
echo "Running command: {$phpunitCmd}\n";

$exitCode = 1;
passthru($phpunitCmd, $exitCode);

echo "\nPHPUnit E2E suite exited with code: {$exitCode}\n";
exit($exitCode);
