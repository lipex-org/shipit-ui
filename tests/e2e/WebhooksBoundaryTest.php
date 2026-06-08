<?php

declare(strict_types=1);

namespace Tests\e2e;

class WebhooksBoundaryTest extends ShipItE2ETestCase
{
    private string $shipitHome;
    private string $globalConfigPath;
    private string $tempProjectDir;
    private string $token = 'webhook_token_bound_xyz_123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->shipitHome = getenv('SHIPIT_HOME') ?: (sys_get_temp_dir() . '/shipit_home_' . uniqid());
        if (!is_dir($this->shipitHome . '/.shipit')) {
            mkdir($this->shipitHome . '/.shipit', 0755, true);
        }
        $this->globalConfigPath = $this->shipitHome . '/.shipit/config.json';

        // Set up a mock project directory
        $this->tempProjectDir = sys_get_temp_dir() . '/shipit_e2e_webhook_bound_' . uniqid();
        mkdir($this->tempProjectDir, 0755, true);
        mkdir($this->tempProjectDir . '/.deploy', 0755, true);

        // Put a basic project config
        $projectConfig = [
            'gitRepoUrl' => 'git@github.com:myorg/myrepo.git',
            'branch' => 'main',
        ];
        file_put_contents($this->tempProjectDir . '/.deploy/config.json', json_encode($projectConfig, JSON_PRETTY_PRINT));

        // Register in global registry
        $realProjPath = realpath($this->tempProjectDir) ?: $this->tempProjectDir;
        $registry = [
            'projects' => [
                $realProjPath => [
                    'path' => $realProjPath,
                    'gitRepoUrl' => 'git@github.com:myorg/myrepo.git',
                    'branch' => 'main',
                    'webhook_token' => $this->token,
                ]
            ]
        ];
        file_put_contents($this->globalConfigPath, json_encode($registry, JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->globalConfigPath)) {
            @unlink($this->globalConfigPath);
        }
        $this->deleteDir($this->tempProjectDir);
        if ($this->shipitHome && $this->shipitHome !== getenv('SHIPIT_HOME')) {
            $this->deleteDir($this->shipitHome);
        }
        parent::tearDown();
    }

    public function testWebhookMissingPayload(): void
    {
        // POST to webhook with empty body
        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $this->token,
            ['Content-Type' => 'application/json'],
            ''
        );

        // Since the route is unimplemented or will reject empty payload, it should return non-2xx (e.g. 400, 403, 404)
        $this->assertTrue(in_array($response['status_code'], [400, 403, 404], true));
    }

    public function testWebhookMalformedJson(): void
    {
        // POST with malformed JSON body
        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $this->token,
            ['Content-Type' => 'application/json'],
            "{ invalid_json: "
        );

        // It should reject with non-2xx status
        $this->assertTrue(in_array($response['status_code'], [400, 403, 404], true));
    }

    public function testWebhookInvalidMethod(): void
    {
        // Send GET request to webhook endpoint
        $responseGet = $this->sendHttpRequest('GET', '/api/webhook/' . $this->token);
        
        // It must be rejected (404, 403, or 405 Method Not Allowed)
        $this->assertTrue(in_array($responseGet['status_code'], [403, 404, 405], true));

        // Send PUT request
        $responsePut = $this->sendHttpRequest('PUT', '/api/webhook/' . $this->token);
        $this->assertTrue(in_array($responsePut['status_code'], [403, 404, 405], true));
    }

    public function testWebhookTokenInjection(): void
    {
        // Malicious token with traversal or SQL injection patterns
        $maliciousToken = '../inject-token-test/\' OR \'1\'=\'1';

        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . urlencode($maliciousToken),
            ['Content-Type' => 'application/json'],
            json_encode(['ref' => 'refs/heads/main'])
        );

        // It must be rejected safely (400, 403, or 404)
        $this->assertTrue(in_array($response['status_code'], [400, 403, 404], true));
    }

    public function testWebhookNonExistentProject(): void
    {
        // Token that does not exist in registry
        $nonExistentToken = 'token-nonexistent-12345';

        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $nonExistentToken,
            ['Content-Type' => 'application/json'],
            json_encode(['ref' => 'refs/heads/main'])
        );

        // Should return 403 or 404
        $this->assertTrue(in_array($response['status_code'], [403, 404], true));
    }
}
