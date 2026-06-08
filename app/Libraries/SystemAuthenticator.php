<?php

namespace App\Libraries;

class SystemAuthenticator
{
    /**
     * Authenticate a user against local Linux credentials.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function authenticate(string $username, string $password): bool
    {
        if (empty($username) || empty($password) || str_starts_with($username, '-')) {
            return false;
        }

        // Support mock Unix authentication in testing environment
        if ((defined('ENVIRONMENT') && ENVIRONMENT === 'testing') || getenv('CI_ENVIRONMENT') === 'testing') {
            $mockUser = getenv('TEST_USER_USERNAME') ?: 'testuser';
            $mockPass = getenv('TEST_USER_PASSWORD') ?: 'testpass';
            if ($username === $mockUser && $password === $mockPass) {
                return true;
            }
        }

        if (!preg_match('/^[a-zA-Z0-9_\.-]+$/', $username)) {
            return false;
        }

        try {
            $pwauthPath = $this->findPwauth();
            if ($pwauthPath !== null) {
                return $this->authenticateWithPwauth($pwauthPath, $username, $password);
            }

            // Fallback: SSH loopback verification
            if ($this->isSsh2ExtensionLoaded()) {
                return $this->authenticateWithSsh2($username, $password);
            }

            $sshpassPath = $this->findSshpass();
            if ($sshpassPath !== null) {
                return $this->authenticateWithSshpass($sshpassPath, $username, $password);
            }
        } catch (\Throwable $e) {
            log_message('error', 'SystemAuthenticator Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return false;
    }

    protected function findPwauth(): ?string
    {
        $paths = ['/usr/sbin/pwauth', '/usr/bin/pwauth'];
        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        $envPath = getenv('PATH');
        if ($envPath) {
            $dirs = explode(PATH_SEPARATOR, $envPath);
            foreach ($dirs as $dir) {
                $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pwauth';
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    protected function authenticateWithPwauth(string $pwauthPath, string $username, string $password): bool
    {
        $descriptorspec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"], // stderr
        ];

        $process = proc_open($pwauthPath, $descriptorspec, $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], "$username\n$password\n");
            fclose($pipes[0]);

            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
            return $exitCode === 0;
        }

        return false;
    }

    protected function authenticateWithSsh2(string $username, string $password): bool
    {
        $connection = @ssh2_connect('127.0.0.1', 22);
        if ($connection) {
            if (@ssh2_auth_password($connection, $username, $password)) {
                return true;
            }
        }
        return false;
    }

    protected function findSshpass(): ?string
    {
        $paths = ['/usr/bin/sshpass', '/usr/sbin/sshpass'];
        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        $envPath = getenv('PATH');
        if ($envPath) {
            $dirs = explode(PATH_SEPARATOR, $envPath);
            foreach ($dirs as $dir) {
                $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sshpass';
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    protected function authenticateWithSshpass(string $sshpassPath, string $username, string $password): bool
    {
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $cmd = [
            $sshpassPath,
            '-e',
            '--',
            'ssh',
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'PreferredAuthentications=password',
            '-o', 'ConnectTimeout=5',
            '--',
            "$username@127.0.0.1",
            'true'
        ];

        $env = array_merge($_ENV, $_SERVER, ['SSHPASS' => $password]);

        $process = proc_open($cmd, $descriptorspec, $pipes, null, $env);
        if (is_resource($process)) {
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
            return $exitCode === 0;
        }

        return false;
    }

    protected function isSsh2ExtensionLoaded(): bool
    {
        return extension_loaded('ssh2');
    }
}
