<?php

namespace App\Controllers;

use ShipIt\ShipIt;

class Projects extends BaseController
{
    public function deploy()
    {
        $projectPath = null;
        try {
            $json = $this->request->getJSON(true);
            if (is_array($json)) {
                $projectPath = $json['project_path'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignore malformed JSON and fallback to POST
        }
        if (empty($projectPath)) {
            $projectPath = $this->request->getPost('project_path');
        }

        if (empty($projectPath)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Project path is required.'
            ])->setStatusCode(400);
        }

        $home = $this->shipit->getHomeDir();
        $globalConfigFile = $home ? $home . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json' : '';

        $projects = [];
        if (!empty($globalConfigFile) && file_exists($globalConfigFile)) {
            $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
            $projects = $registry['projects'] ?? [];
        }

        $resolvedPath = realpath($projectPath);
        if (!$resolvedPath || !isset($projects[$resolvedPath])) {
            if (!isset($projects[$projectPath])) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Project not registered in global registry.'
                ])->setStatusCode(400);
            }
            $resolvedPath = $projectPath;
        }

        // Enforce Permissions
        $this->shipit->setRoot($resolvedPath);
        $this->shipit->loadConfig();
        if (!$this->canManageProject($resolvedPath, $this->shipit->getConfig())) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'You do not have permission to manage this project.'
            ])->setStatusCode(403);
        }

        $logId = uniqid('deploy_', true);
        $logDirectory = WRITEPATH . 'logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }
        $logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $logId . '.log';
        touch($logFilePath);

        $escapedProjectPath = escapeshellarg($resolvedPath);
        $escapedLogPath = escapeshellarg($logFilePath);
        $escapedLogId = escapeshellarg($logId);

        $runner = getenv('SHIPIT_RUNNER') ?: 'php ' . escapeshellarg(ROOTPATH . 'spark') . ' shipit:run';

        // Use the runner to execute via the ShipIt class (defaults to php spark shipit:run)
        $cmd = "nohup sh -c \"{$runner} --project {$escapedProjectPath} --command deploy --log-id {$escapedLogId} > {$escapedLogPath} 2>&1 ; echo '[FINISHED]' >> {$escapedLogPath}\" > /dev/null 2>&1 &";
        shell_exec($cmd);

        return $this->response->setJSON([
            'status' => 'started',
            'log_id' => $logId
        ]);
    }

    public function rollback()
    {
        $projectPath = null;
        $backup = null;
        try {
            $json = $this->request->getJSON(true);
            if (is_array($json)) {
                $projectPath = $json['project_path'] ?? null;
                $backup = $json['backup'] ?? null;
            }
        } catch (\Exception $e) {
            // Fallback
        }

        if (empty($projectPath)) {
            $projectPath = $this->request->getPost('project_path');
        }
        if (empty($backup)) {
            $backup = $this->request->getPost('backup');
        }

        if (empty($projectPath)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Project path is required.'
            ])->setStatusCode(400);
        }

        if (empty($backup)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Backup timestamp is required.'
            ])->setStatusCode(400);
        }

        if (!preg_match('/^\d{8}_\d{6}$/', $backup)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid backup timestamp format.'
            ])->setStatusCode(400);
        }

        $home = $this->shipit->getHomeDir();
        $globalConfigFile = $home ? $home . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json' : '';

        $projects = [];
        if (!empty($globalConfigFile) && file_exists($globalConfigFile)) {
            $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
            $projects = $registry['projects'] ?? [];
        }

        $resolvedPath = realpath($projectPath);
        if (!$resolvedPath || !isset($projects[$resolvedPath])) {
            if (!isset($projects[$projectPath])) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Project not registered in global registry.'
                ])->setStatusCode(400);
            }
            $resolvedPath = $projectPath;
        }

        // Enforce Permissions
        $this->shipit->setRoot($resolvedPath);
        $this->shipit->loadConfig();
        if (!$this->canManageProject($resolvedPath, $this->shipit->getConfig())) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'You do not have permission to manage this project.'
            ])->setStatusCode(403);
        }

        $logId = uniqid('rollback_', true);
        $logDirectory = WRITEPATH . 'logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }
        $logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $logId . '.log';
        touch($logFilePath);

        $escapedProjectPath = escapeshellarg($resolvedPath);
        $escapedBackup = escapeshellarg($backup);
        $escapedLogPath = escapeshellarg($logFilePath);
        $escapedLogId = escapeshellarg($logId);

        $runner = getenv('SHIPIT_RUNNER') ?: 'php ' . escapeshellarg(ROOTPATH . 'spark') . ' shipit:run';

        // Use the runner to execute via the ShipIt class (defaults to php spark shipit:run)
        $cmd = "nohup sh -c \"{$runner} --project {$escapedProjectPath} --command rollback --backup {$escapedBackup} --log-id {$escapedLogId} > {$escapedLogPath} 2>&1 ; echo '[FINISHED]' >> {$escapedLogPath}\" > /dev/null 2>&1 &";
        shell_exec($cmd);

        return $this->response->setJSON([
            'status' => 'started',
            'log_id' => $logId
        ]);
    }

    public function init()
    {
        $json = $this->request->getJSON(true);
        $projectPath = $json['project_path'] ?? null;
        $gitUrl = $json['git_url'] ?? null;
        $branch = $json['branch'] ?? 'main';

        if (empty($projectPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project path is required.'])->setStatusCode(400);
        }

        // Enforce Permissions (if directory exists)
        if (is_dir($projectPath)) {
            $this->shipit->setRoot($projectPath);
            // We don't loadConfig here because we are initializing, but we check filesystem owner
            if (!$this->canManageProject($projectPath)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'You do not have permission to initialize this directory.'
                ])->setStatusCode(403);
            }
        }

        $logId = uniqid('init_', true);
        $logDirectory = WRITEPATH . 'logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }
        $logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $logId . '.log';
        touch($logFilePath);

        $escapedProjectPath = escapeshellarg($projectPath);
        $escapedGitUrl = escapeshellarg($gitUrl);
        $escapedBranch = escapeshellarg($branch);
        $escapedLogPath = escapeshellarg($logFilePath);
        $escapedLogId = escapeshellarg($logId);
        $escapedUser = escapeshellarg(session()->get('username') ?: 'admin');
        
        $runner = getenv('SHIPIT_RUNNER') ?: 'php ' . escapeshellarg(ROOTPATH . 'spark') . ' shipit:run';

        // Background execution: init command with git-url, branch, user, and force
        $cmd = "nohup sh -c \"{$runner} --project {$escapedProjectPath} --command init --git-url {$escapedGitUrl} --branch {$escapedBranch} --user {$escapedUser} --log-id {$escapedLogId} --force > {$escapedLogPath} 2>&1 ; echo '[FINISHED]' >> {$escapedLogPath}\" > /dev/null 2>&1 &";
        shell_exec($cmd);

        return $this->response->setJSON([
            'status' => 'started',
            'log_id' => $logId
        ]);
    }

    public function regenerateWebhookToken()
    {
        $projectPath = null;
        try {
            $json = $this->request->getJSON(true);
            if (is_array($json)) {
                $projectPath = $json['project_path'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignore malformed JSON and fallback to POST
        }
        if (empty($projectPath)) {
            $projectPath = $this->request->getPost('project_path');
        }

        if (empty($projectPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project path is required.'])->setStatusCode(400);
        }

        $shipit = new ShipIt();
        $home = $shipit->getHomeDir();
        $globalConfigFile = $home ? $home . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json' : '';

        if (!file_exists($globalConfigFile)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Global registry not found.'])->setStatusCode(404);
        }

        $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
        $projects = $registry['projects'] ?? [];

        // Support both realpath and provided path
        $resolvedPath = realpath($projectPath);
        $pathKey = ($resolvedPath && isset($projects[$resolvedPath])) ? $resolvedPath : $projectPath;

        if (!isset($projects[$pathKey])) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project not found in registry.'])->setStatusCode(404);
        }

        // Enforce Permissions
        $this->shipit->setRoot($pathKey);
        $this->shipit->loadConfig();
        if (!$this->canManageProject($pathKey, $this->shipit->getConfig())) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'You do not have permission to manage this project.'
            ])->setStatusCode(403);
        }

        $newToken = bin2hex(random_bytes(16));
        $registry['projects'][$pathKey]['webhook_token'] = $newToken;

        if (file_put_contents($globalConfigFile, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to update registry.'])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Webhook token regenerated successfully.',
            'new_token' => $newToken
        ]);
    }

    public function getEnv()
    {
        $encodedPath = $this->request->getGet('path');
        if (empty($encodedPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project path is required.'])->setStatusCode(400);
        }

        $projectPath = base64_decode($encodedPath);
        if (!$projectPath || !is_dir($projectPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid project path.'])->setStatusCode(400);
        }

        // Validate path against registry
        $home = $this->shipit->getHomeDir();
        $globalConfigFile = $home ? $home . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json' : '';
        if (!file_exists($globalConfigFile)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Registry not found.'])->setStatusCode(404);
        }

        $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
        $projects = $registry['projects'] ?? [];
        
        $resolvedPath = realpath($projectPath);
        if (!$resolvedPath || (!isset($projects[$resolvedPath]) && !isset($projects[$projectPath]))) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project not registered.'])->setStatusCode(403);
        }

        // Enforce Permissions
        $this->shipit->setRoot($projectPath);
        $this->shipit->loadConfig();
        if (!$this->canManageProject($projectPath, $this->shipit->getConfig())) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'You do not have permission to manage this project.'
            ])->setStatusCode(403);
        }

        $envFile = ($resolvedPath ?: $projectPath) . DIRECTORY_SEPARATOR . '.env';
        $content = file_exists($envFile) ? file_get_contents($envFile) : '';

        return $this->response->setJSON([
            'status' => 'success',
            'content' => $content
        ]);
    }

    public function updateEnv()
    {
        $projectPath = null;
        $content = '';
        try {
            $json = $this->request->getJSON(true);
            if (is_array($json)) {
                $projectPath = $json['project_path'] ?? null;
                $content = $json['content'] ?? '';
            }
        } catch (\Exception $e) {
            // Fallback
        }
        
        if (empty($projectPath)) {
            $projectPath = $this->request->getPost('project_path');
            $content = $this->request->getPost('content') ?? '';
        }

        if (empty($projectPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project path is required.'])->setStatusCode(400);
        }

        // Validate path against registry
        $home = $this->shipit->getHomeDir();
        $globalConfigFile = $home ? $home . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json' : '';
        if (!file_exists($globalConfigFile)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Registry not found.'])->setStatusCode(404);
        }

        $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
        $projects = $registry['projects'] ?? [];
        
        $resolvedPath = realpath($projectPath);
        if (!$resolvedPath || (!isset($projects[$resolvedPath]) && !isset($projects[$projectPath]))) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project not registered.'])->setStatusCode(403);
        }

        // Enforce Permissions
        $this->shipit->setRoot($projectPath);
        $this->shipit->loadConfig();
        if (!$this->canManageProject($projectPath, $this->shipit->getConfig())) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'You do not have permission to manage this project.'
            ])->setStatusCode(403);
        }

        $envFile = ($resolvedPath ?: $projectPath) . DIRECTORY_SEPARATOR . '.env';
        
        if (file_put_contents($envFile, $content) === false) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to write to .env file.'])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => '.env file updated successfully.'
        ]);
    }

    public function getProjectConfig()
    {
        $encodedPath = $this->request->getGet('path');
        if (empty($encodedPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project path is required.'])->setStatusCode(400);
        }

        $projectPath = base64_decode($encodedPath);
        if (!$projectPath || !is_dir($projectPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid project path.'])->setStatusCode(400);
        }

        // Validate path against registry
        $home = $this->shipit->getHomeDir();
        $globalConfigFile = $home ? $home . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json' : '';
        if (!file_exists($globalConfigFile)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Registry not found.'])->setStatusCode(404);
        }

        $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
        $projects = $registry['projects'] ?? [];
        
        $resolvedPath = realpath($projectPath);
        if (!$resolvedPath || (!isset($projects[$resolvedPath]) && !isset($projects[$projectPath]))) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project not registered.'])->setStatusCode(403);
        }

        $configFile = ($resolvedPath ?: $projectPath) . DIRECTORY_SEPARATOR . '.deploy' . DIRECTORY_SEPARATOR . 'config.json';
        if (!file_exists($configFile)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Configuration file (.deploy/config.json) not found.'])->setStatusCode(404);
        }

        $config = json_decode(file_get_contents($configFile), true) ?: [];

        return $this->response->setJSON([
            'status' => 'success',
            'config' => $config
        ]);
    }

    public function updateProjectConfig()
    {
        $json = $this->request->getJSON(true);
        $projectPath = null;
        try {
            if (is_array($json)) {
                $projectPath = $json['project_path'] ?? null;
            }
        } catch (\Exception $e) {}

        if (empty($projectPath)) {
            $projectPath = $this->request->getPost('project_path');
        }

        if (empty($projectPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project path is required.'])->setStatusCode(400);
        }

        // Resolve Path
        $resolvedPath = realpath($projectPath);
        $pathKey = $resolvedPath ?: $projectPath;

        // Enforce Permissions
        $this->shipit->setRoot($pathKey);
        $this->shipit->loadConfig();
        if (!$this->canManageProject($pathKey, $this->shipit->getConfig())) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'You do not have permission to manage this project.'
            ])->setStatusCode(403);
        }

        $configFile = $pathKey . DIRECTORY_SEPARATOR . '.deploy' . DIRECTORY_SEPARATOR . 'config.json';
        if (!file_exists($configFile)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Configuration file not found.'])->setStatusCode(404);
        }

        $currentConfig = json_decode(file_get_contents($configFile), true) ?: [];
        
        // Fields we allow updating through the form
        $updatable = ['adapter', 'server', 'gitRepoUrl', 'branch', 'user', 'group', 'backup_path', 'backup_retention'];
        foreach ($updatable as $field) {
            if (isset($json[$field])) {
                $currentConfig[$field] = $json[$field];
            } elseif ($this->request->getPost($field) !== null) {
                $currentConfig[$field] = $this->request->getPost($field);
            }
        }

        // Numeric type casting
        if (isset($currentConfig['backup_retention'])) {
            $currentConfig['backup_retention'] = (int) $currentConfig['backup_retention'];
        }

        // Save back to project config
        if (file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to write to config.json.'])->setStatusCode(500);
        }

        // Trigger a registry update to reflect potential Git URL or Branch changes
        $this->shipit->updateGlobalRegistry();

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Project configuration updated successfully.'
        ]);
    }

    public function validateLog()
    {
        $encodedPath = $this->request->getGet('path');

        if (empty($encodedPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project path is required.']);
        }

        // Actually encodedPath here is the project path (base64 encoded)
        $projectPath = base64_decode($encodedPath);

        if (!$projectPath || !is_dir($projectPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid project path.']);
        }

        // Create shipit instance for the project
        $shipit = new ShipIt($projectPath);
        $shipit->loadConfig();

        $results = $shipit->validator->validate($shipit->getConfig(), $projectPath);

        return $this->response->setJSON([
            'status' => 'success',
            'results' => $results
        ]);
    }

    /**
     * Polling-based log reader.
     * Returns new lines since the provided offset.
     */
    public function logs(string $logId)
    {
        if (!preg_match('/^[a-zA-Z0-9_\.-]+$/', $logId)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid log ID.'
            ])->setStatusCode(400);
        }

        $logFilePath = WRITEPATH . 'logs/' . $logId . '.log';
        $offset = (int) $this->request->getGet('offset') ?: 0;

        if (!file_exists($logFilePath)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Log file not found.'
            ])->setStatusCode(404);
        }

        $fp = fopen($logFilePath, 'r');
        if (!$fp) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to open log file.'
            ])->setStatusCode(500);
        }

        // Seek to the last read position
        fseek($fp, $offset);

        $lines = [];
        $finished = false;

        while (($line = fgets($fp)) !== false) {
            $trimmed = rtrim($line, "\r\n");
            $lines[] = $trimmed;
            if (str_contains($trimmed, '[FINISHED]')) {
                $finished = true;
            }
        }

        $newOffset = ftell($fp);
        fclose($fp);

        return $this->response->setJSON([
            'status' => 'success',
            'lines' => $lines,
            'offset' => $newOffset,
            'finished' => $finished
        ]);
    }
}
