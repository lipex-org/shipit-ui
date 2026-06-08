<?php

declare(strict_types=1);

namespace Tests\e2e;

use PHPUnit\Framework\TestCase;

class ShipItE2ETestCase extends TestCase
{
    protected ?string $cookieFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        $home = getenv('HOME');
        $shipitHome = getenv('SHIPIT_HOME');

        if (empty($home) || empty($shipitHome)) {
            throw new \RuntimeException("Danger: SHIPIT_HOME or HOME environment variable is empty. Test execution aborted to protect host environment.");
        }

        if (!file_exists($home)) {
            @mkdir($home, 0755, true);
        }
        if (!file_exists($shipitHome)) {
            @mkdir($shipitHome, 0755, true);
        }

        $realHome = null;
        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $userInfo = posix_getpwuid(posix_getuid());
            if ($userInfo && isset($userInfo['dir'])) {
                $realHome = realpath($userInfo['dir']);
            }
        }
        if (!$realHome) {
            $realHome = realpath($_SERVER['HOME'] ?? $_ENV['HOME'] ?? '');
        }

        $realHome = $realHome ? rtrim($realHome, '/') : null;
        $resolvedHome = realpath($home);
        $resolvedShipitHome = realpath($shipitHome);

        if ($realHome && ($resolvedHome === $realHome || $resolvedShipitHome === $realHome)) {
            throw new \RuntimeException("Danger: HOME or SHIPIT_HOME points to the real user home directory ($realHome). Test execution aborted to protect host environment.");
        }

        $tempDir = realpath(sys_get_temp_dir());
        if ($tempDir) {
            $tempDir = rtrim($tempDir, '/');
            if ($resolvedHome === false || strpos($resolvedHome, $tempDir) !== 0 ||
                $resolvedShipitHome === false || strpos($resolvedShipitHome, $tempDir) !== 0) {
                throw new \RuntimeException("Danger: HOME or SHIPIT_HOME is not located in the temporary directory ($tempDir). Test execution aborted.");
            }
        }

        // Prepare a temporary cookie file for session persistence
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'shipit_e2e_cookie_');
    }

    protected function tearDown(): void
    {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        parent::tearDown();
    }

    /**
     * Recursively delete a directory to avoid leaks.
     */
    protected function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Execute a CLI command on `bin/shipit` under the sandboxed environment.
     *
     * @param array $args Arguments to pass to the CLI.
     * @param string|null $stdinInput Optional input to write to stdin.
     * @return array Array containing stdout, stderr, and exit_code.
     */
    protected function runCliCommand(array $args, ?string $stdinInput = null): array
    {
        $binPath = realpath(__DIR__ . '/../../../package/bin/shipit');
        if ($binPath === false) {
            throw new \RuntimeException("bin/shipit not found");
        }

        // Build CLI command using escapeshellarg for safety
        $command = 'php ' . escapeshellarg($binPath);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        // Explicitly set HOME and SHIPIT_HOME to matching sandboxed paths
        $env = array_merge($_ENV, [
            'HOME' => getenv('HOME'),
            'SHIPIT_HOME' => getenv('SHIPIT_HOME'),
        ]);

        $process = proc_open($command, $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            throw new \RuntimeException("Failed to execute CLI command: {$command}");
        }

        if ($stdinInput !== null) {
            fwrite($pipes[0], $stdinInput);
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Send an HTTP request to the target test server.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Target URI path (e.g. '/login')
     * @param array $headers Associative array of request headers
     * @param string|null $body Request body
     * @return array Array containing status_code, headers, and body.
     */
    protected function sendHttpRequest(string $method, string $path, array $headers = [], ?string $body = null): array
    {
        $serverUrl = getenv('TEST_SERVER_URL');
        if (!$serverUrl) {
            throw new \RuntimeException("TEST_SERVER_URL environment variable is not set");
        }

        $url = rtrim($serverUrl, '/') . '/' . ltrim($path, '/');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        if ($this->cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = "{$name}: {$value}";
        }
        
        if ($body !== null) {
            $formattedHeaders[] = 'Content-Length: ' . strlen($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("HTTP request failed to URL {$url}: {$error}");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        return [
            'status_code' => $httpCode,
            'headers' => $responseHeaders,
            'body' => $responseBody,
        ];
    }
}
