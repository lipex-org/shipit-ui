<?php

namespace App\Controllers;

use ShipIt\ShipIt;

class Api extends BaseController
{
    /**
     * POST /api/webhook/<token>
     * 
     * Secure this endpoint by checking <token> against the webhook_token field
     * of projects in the global registry ~/.shipit/config.json.
     */
    public function webhook(string $token)
    {
        $home = $this->shipit->getHomeDir();
        $globalConfigFile = $home ? $home . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json' : '';

        $matchedProject = null;
        $matchedPath = null;
        if (!empty($globalConfigFile) && file_exists($globalConfigFile)) {
            $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
            $projects = $registry['projects'] ?? [];
            foreach ($projects as $path => $project) {
                if (isset($project['webhook_token']) && hash_equals($project['webhook_token'], $token)) {
                    $matchedProject = $project;
                    $matchedPath = $path;
                    break;
                }
            }
        }

        // If no project matches the token, return HTTP 404 Not Found.
        if (!$matchedProject) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid webhook token'
            ])->setStatusCode(404);
        }

        // Parse the push payload from the Git provider.
        $rawBody = $this->request->getBody();
        $contentType = $this->request->getHeaderLine('Content-Type');

        $isPing = false;
        if (!empty($rawBody)) {
            $json = json_decode($rawBody, true);
            if ($json !== null && isset($json['zen'])) {
                $isPing = true;
            }
        }
        if ($this->request->getHeaderLine('X-GitHub-Event') === 'ping') {
            $isPing = true;
        }

        if ($isPing) {
            return $this->response->setJSON([
                'status' => 'ignored',
                'reason' => 'branch mismatch or non-push event'
            ])->setStatusCode(202);
        }

        $projectBranch = $matchedProject['branch'] ?? 'main';
        $trigger = true;

        if (!empty($rawBody)) {
            $json = json_decode($rawBody, true);
            if ($json !== null) {
                if (isset($json['ref'])) {
                    $ref = $json['ref'];
                    $payloadBranch = $ref;
                    if (strpos($ref, 'refs/heads/') === 0) {
                        $payloadBranch = substr($ref, 11);
                    }
                    if ($payloadBranch !== $projectBranch) {
                        $trigger = false;
                    }
                } else {
                    $trigger = true;
                }
            } else {
                if (str_contains(strtolower($contentType), 'json')) {
                    $trimmed = trim($rawBody);
                    if ($trimmed !== '') {
                        return $this->response->setJSON([
                            'status' => 'error',
                            'message' => 'Invalid JSON payload'
                        ])->setStatusCode(400);
                    }
                }
                $trigger = true;
            }
        } else {
            $trigger = true;
        }

        // If the branches match (or if the payload is empty/missing):
        if ($trigger) {
            $timestamp = time();
            $logId = "webhook_{$token}_{$timestamp}";

            $projectPath = $matchedProject['path'] ?? $matchedPath;

            $logDirectory = WRITEPATH . 'logs';
            if (!is_dir($logDirectory)) {
                mkdir($logDirectory, 0777, true);
            }
            $logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $logId . '.log';
            touch($logFilePath);

            $escapedProjectPath = escapeshellarg($projectPath);
            $escapedLogPath = escapeshellarg($logFilePath);
            $escapedLogId = escapeshellarg($logId);
            
            $runner = getenv('SHIPIT_RUNNER') ?: 'php ' . escapeshellarg(ROOTPATH . 'spark') . ' shipit:run';

            // Execute the deployment in background via the runner (defaults to php spark shipit:run)
            $cmd = "nohup sh -c \"{$runner} --project {$escapedProjectPath} --command deploy --log-id {$escapedLogId} > {$escapedLogPath} 2>&1 ; echo '[FINISHED]' >> {$escapedLogPath}\" > /dev/null 2>&1 &";
            shell_exec($cmd);

            return $this->response->setJSON([
                'status' => 'started',
                'log_id' => $logId
            ])->setStatusCode(202);
        }

        // If the branch does NOT match, skip and return HTTP 202 (or 200) with JSON response
        return $this->response->setJSON([
            'status' => 'skipped',
            'reason' => 'branch mismatch'
        ])->setStatusCode(202);
    }
}
