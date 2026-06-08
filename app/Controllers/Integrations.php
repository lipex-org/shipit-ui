<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Integrations extends BaseController
{
    /**
     * Redirects to GitHub to start the OAuth flow.
     */
    public function githubConnect()
    {
        $clientId = env('GITHUB_CLIENT_ID');
        if (empty($clientId)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'GitHub Client ID not configured.'])->setStatusCode(500);
        }

        $callback = site_url('integrations/github/callback');
        // Scope 'repo' allows listing private and public repositories
        // Scope 'admin:repo_hook' allows creating webhooks
        $url = "https://github.com/login/oauth/authorize?client_id={$clientId}&redirect_uri=" . urlencode($callback) . "&scope=repo,admin:repo_hook";

        return redirect()->to($url);
    }

    /**
     * Handles the callback from GitHub, exchanges code for token, and saves it.
     */
    public function githubCallback()
    {
        $code = $this->request->getGet('code');
        if (empty($code)) {
            return redirect()->to('/dashboard')->with('error', 'GitHub authorization failed.');
        }

        $clientId = env('GITHUB_CLIENT_ID');
        $clientSecret = env('GITHUB_CLIENT_SECRET');

        $client = \Config\Services::curlrequest();
        
        try {
            $response = $client->post('https://github.com/login/oauth/access_token', [
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $token = $data['access_token'] ?? null;

            if ($token) {
                $username = session()->get('username');
                $this->shipit->setUserIntegrationToken($username, 'github', $token);
                return redirect()->to('/dashboard')->with('message', 'Successfully connected GitHub!');
            }

            return redirect()->to('/dashboard')->with('error', 'Failed to obtain access token.');

        } catch (\Exception $e) {
            return redirect()->to('/dashboard')->with('error', 'Error during GitHub connection: ' . $e->getMessage());
        }
    }

    /**
     * API Proxy to list the user's GitHub repositories.
     */
    public function githubRepos()
    {
        $username = session()->get('username');
        $token = $this->shipit->getUserIntegrationToken($username, 'github');

        if (!$token) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'GitHub not connected.', 'connected' => false])->setStatusCode(401);
        }

        $client = \Config\Services::curlrequest();
        
        try {
            // Fetch repositories sorted by recently updated
            $response = $client->get('https://api.github.com/user/repos?sort=updated&per_page=100', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'ShipIt-Control-Panel'
                ]
            ]);

            $repos = json_decode($response->getBody(), true);
            
            $formattedRepos = array_map(function($repo) {
                return [
                    'name' => $repo['full_name'],
                    'url' => $repo['ssh_url'], // Prefer SSH for ShipIt
                    'clone_url' => $repo['clone_url'],
                    'branch' => $repo['default_branch'],
                    'private' => $repo['private']
                ];
            }, $repos);

            return $this->response->setJSON([
                'status' => 'success',
                'connected' => true,
                'repos' => $formattedRepos
            ]);

        } catch (\Exception $e) {
            // If token is invalid, we might want to clear it
            return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to fetch repositories: ' . $e->getMessage()])->setStatusCode(500);
        }
    }

    /**
     * Automatically configures a GitHub webhook for a project.
     */
    public function setupWebhook()
    {
        $json = $this->request->getJSON(true);
        $projectPath = $json['project_path'] ?? null;

        if (empty($projectPath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project path is required.'])->setStatusCode(400);
        }

        // 1. Validate permissions and get project details
        $resolvedPath = realpath($projectPath);
        $pathKey = $resolvedPath ?: $projectPath;

        $home = $this->shipit->getHomeDir();
        $globalConfigFile = $home ? $home . DIRECTORY_SEPARATOR . '.shipit' . DIRECTORY_SEPARATOR . 'config.json' : '';
        if (!file_exists($globalConfigFile)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Registry not found.'])->setStatusCode(404);
        }

        $registry = json_decode(file_get_contents($globalConfigFile), true) ?: [];
        $projects = $registry['projects'] ?? [];
        $project = $projects[$pathKey] ?? null;

        if (!$project) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Project not found in registry.'])->setStatusCode(404);
        }

        if (!$this->canManageProject($pathKey, $project)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Permission denied.'])->setStatusCode(403);
        }

        // 2. Get GitHub Token
        $username = session()->get('username');
        $token = $this->shipit->getUserIntegrationToken($username, 'github');
        if (!$token) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'GitHub not connected.'])->setStatusCode(401);
        }

        // 3. Parse Repo Owner/Name
        $repoUrl = $project['gitRepoUrl'] ?? '';
        $repoName = $this->parseGithubRepo($repoUrl);
        if (!$repoName) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid GitHub repository URL.'])->setStatusCode(400);
        }

        // 4. Construct Webhook URL
        $webhookToken = $project['webhook_token'] ?? null;
        if (!$webhookToken) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Webhook token not found.'])->setStatusCode(500);
        }
        $callbackUrl = site_url('api/webhook/' . $webhookToken);

        // 5. Call GitHub API to create webhook
        $client = \Config\Services::curlrequest();
        try {
            $response = $client->post("https://api.github.com/repos/{$repoName}/hooks", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'ShipIt-Control-Panel'
                ],
                'json' => [
                    'name' => 'web',
                    'active' => true,
                    'events' => ['push'],
                    'config' => [
                        'url' => $callbackUrl,
                        'content_type' => 'json',
                        'insecure_ssl' => '0'
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if ($response->getStatusCode() === 201) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'message' => 'GitHub webhook configured successfully.'
                ]);
            }

            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'GitHub API returned an error: ' . ($result['message'] ?? 'Unknown error')
            ])->setStatusCode($response->getStatusCode());

        } catch (\Exception $e) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to setup webhook: ' . $e->getMessage()])->setStatusCode(500);
        }
    }

    /**
     * Helper to parse owner/repo from GitHub URLs.
     */
    private function parseGithubRepo(string $url): ?string
    {
        // Handle SSH: git@github.com:owner/repo.git
        if (preg_match('/github\.com[:\/](.+?)\/(.+?)(?:\.git)?$/', $url, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        // Handle HTTPS: https://github.com/owner/repo.git
        if (preg_match('/github\.com\/(.+?)\/(.+?)(?:\.git)?$/', $url, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        return null;
    }
}
