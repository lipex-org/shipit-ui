<?php

namespace App\Controllers;

use ShipIt\ShipIt;

class Dashboard extends BaseController
{
    public function index()
    {
        $home = $this->shipit->getHomeDir();
        $globalConfigFile = $home ? $home . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json' : '';

        $projects = [];
        if (!empty($globalConfigFile) && file_exists($globalConfigFile)) {
            $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
            $projects = $registry['projects'] ?? [];
        }

        $search = $this->request->getGet('search');
        if (!empty($search)) {
            $filtered = [];
            foreach ($projects as $path => $project) {
                $match = false;
                if (stripos($path, $search) !== false) {
                    $match = true;
                } elseif (isset($project['path']) && stripos($project['path'], $search) !== false) {
                    $match = true;
                } elseif (isset($project['gitRepoUrl']) && stripos($project['gitRepoUrl'], $search) !== false) {
                    $match = true;
                } elseif (isset($project['branch']) && stripos($project['branch'], $search) !== false) {
                    $match = true;
                }
                if ($match) {
                    $filtered[$path] = $project;
                }
            }
            $projects = $filtered;
        }

        $projectsWithBackups = [];
        foreach ($projects as $path => $project) {
            try {
                // Use the shipit instance to resolve the full configuration for this project
                $this->shipit->setRoot($path);
                $this->shipit->loadConfig();
                $config = $this->shipit->getConfig();
                
                // Enforce permissions: Only show projects the user can manage
                if (!$this->canManageProject($path, $config)) {
                    continue;
                }

                $backupPath = $config['backup_path'] ?? null;
                $backups = [];

                if (!empty($backupPath) && is_dir($backupPath)) {
                    $dirs = array_filter(glob($backupPath . DIRECTORY_SEPARATOR . 'backup_*'), 'is_dir');
                    foreach ($dirs as $dir) {
                        $name = basename($dir);
                        $timestamp = substr($name, 7);
                        if (!empty($timestamp)) {
                            $backups[] = $timestamp;
                        }
                    }
                    rsort($backups);
                }

                $project['backups'] = $backups;
                $project['gitRepoUrl'] = $config['gitRepoUrl'] ?? $project['gitRepoUrl'] ?? 'N/A';
                $project['branch'] = $config['branch'] ?? $project['branch'] ?? 'main';
                
            } catch (\Exception $e) {
                // If a project path is invalid or inaccessible, keep existing data and empty backups
                $project['backups'] = [];
            }
            
            $projectsWithBackups[$path] = $project;
        }

        return page('dashboard', [
            'projects' => $projectsWithBackups,
            'username' => session()->get('username'),
        ]);
    }

    public function prune()
    {
        // Since we want to return JSON for the UI:
        $globalConfigFile = $this->shipit->getHomeDir() . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json';

        if (!file_exists($globalConfigFile)) {
            return $this->response->setJSON(['status' => 'info', 'message' => 'Registry not found.']);
        }

        $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
        if (!isset($registry['projects'])) {
            return $this->response->setJSON(['status' => 'info', 'message' => 'No projects in registry.']);
        }

        $prunedCount = 0;
        foreach ($registry['projects'] as $path => $data) {
            if (!is_dir($path)) {
                unset($registry['projects'][$path]);
                $prunedCount++;
            }
        }

        if ($prunedCount > 0) {
            file_put_contents($globalConfigFile, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $this->response->setJSON([
                'status' => 'success',
                'message' => "Successfully pruned $prunedCount dead project(s)."
            ]);
        }

        return $this->response->setJSON(['status' => 'success', 'message' => 'Registry is already clean.']);
    }
}
