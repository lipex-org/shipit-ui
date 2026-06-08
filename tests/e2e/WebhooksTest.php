<?php

declare(strict_types=1);

namespace Tests\e2e;

class WebhooksTest extends ShipItE2ETestCase
{
    private string $shipitHome;
    private string $globalConfigPath;
    private string $tempProjectDir;
    private string $token = 'webhook_token_xyz_123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->shipitHome = getenv('SHIPIT_HOME') ?: (sys_get_temp_dir() . '/shipit_home_' . uniqid());
        if (!is_dir($this->shipitHome . '/.shipit')) {
            mkdir($this->shipitHome . '/.shipit', 0755, true);
        }
        $this->globalConfigPath = $this->shipitHome . '/.shipit/config.json';

        // Set up a mock project directory
        $this->tempProjectDir = sys_get_temp_dir() . '/shipit_e2e_webhook_' . uniqid();
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

    public function testValidWebhookTokenTriggersDeploy(): void
    {
        $payload = [
            'ref' => 'refs/heads/main',
            'repository' => [
                'url' => 'https://github.com/myorg/myrepo'
            ]
        ];

        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $this->token,
            ['Content-Type' => 'application/json'],
            json_encode($payload)
        );

        $this->assertSame(202, $response['status_code'], "Valid webhook token must trigger deployment and return 202 Accepted");
    }

    public function testWebhookIsNonBlocking(): void
    {
        $payload = [
            'ref' => 'refs/heads/main',
        ];

        $startTime = microtime(true);
        
        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $this->token,
            ['Content-Type' => 'application/json'],
            json_encode($payload)
        );

        $duration = microtime(true) - $startTime;

        $this->assertLessThan(1.0, $duration, "Webhook response was blocking, took too long: {$duration}s");
        $this->assertSame(202, $response['status_code']);
    }

    public function testInvalidWebhookTokenRejected(): void
    {
        $payload = [
            'ref' => 'refs/heads/main',
        ];

        $response = $this->sendHttpRequest(
            'POST',
            '/api/webhook/invalid_token_123',
            ['Content-Type' => 'application/json'],
            json_encode($payload)
        );

        // Invalid token should be rejected with 403 Forbidden or 404 Not Found
        $this->assertTrue(
            $response['status_code'] === 403 || $response['status_code'] === 404,
            "Invalid webhook token was not rejected with 403 or 404. Status: " . $response['status_code']
        );
    }

    public function testWebhookBranchFilter(): void
    {
        // Push notification on non-matching branch should be ignored/filtered
        $payloadWrongBranch = [
            'ref' => 'refs/heads/feature-x',
        ];

        $responseWrong = $this->sendHttpRequest(
            'POST',
            '/api/webhook/' . $this->token,
            ['Content-Type' => 'application/json'],
            json_encode($payloadWrongBranch)
        );

        // It should either return 200/202 with an ignored status or not trigger deploy
        // Usually, webhooks return 202 accepted or 200 ok for ignored branches.
        // We check that the response indicates the push was ignored or not deployed.
        $this->assertTrue(
            $responseWrong['status_code'] === 200 || $responseWrong['status_code'] === 202,
            "Webhook should accept the request even if branch doesn't match"
        );
        
        // Assert that the body indicates it was ignored or not triggered
        $this->assertTrue(
            str_contains(strtolower($responseWrong['body']), 'ignored') || 
            str_contains(strtolower($responseWrong['body']), 'skipped'),
            "Push to wrong branch was not ignored or skipped"
        );
    }

    public function testConcurrentWebhooksQueued(): void
    {
        // Trigger multiple webhooks rapidly
        $payload = [
            'ref' => 'refs/heads/main',
        ];

        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->sendHttpRequest(
                'POST',
                '/api/webhook/' . $this->token,
                ['Content-Type' => 'application/json'],
                json_encode($payload)
            );
        }

        // Each should return 202 Accepted immediately
        foreach ($responses as $response) {
            $this->assertSame(202, $response['status_code']);
            $json = json_decode($response['body'], true);
            $this->assertIsArray($json);
            // Queued webhooks should return some indication of acceptance or queuing
            $this->assertTrue(
                isset($json['status']) && 
                ($json['status'] === 'queued' || $json['status'] === 'accepted' || $json['status'] === 'started'),
                "Concurrent webhooks did not return a queued/accepted status"
            );
        }
    }
}
