<?php

use App\Libraries\SystemAuthenticator;
use CodeIgniter\Test\CIUnitTestCase;

class MockSystemAuthenticator extends SystemAuthenticator
{
    public ?string $mockPwauthPath = null;
    public ?string $mockSshpassPath = null;
    public ?bool $mockSsh2Loaded = null;
    public ?bool $mockPwauthResult = null;
    public ?bool $mockSsh2Result = null;
    public ?bool $mockSshpassResult = null;

    protected function findPwauth(): ?string
    {
        return $this->mockPwauthPath;
    }

    protected function findSshpass(): ?string
    {
        return $this->mockSshpassPath;
    }

    protected function isSsh2ExtensionLoaded(): bool
    {
        return $this->mockSsh2Loaded ?? false;
    }

    protected function authenticateWithPwauth(string $pwauthPath, string $username, string $password): bool
    {
        return $this->mockPwauthResult ?? false;
    }

    protected function authenticateWithSsh2(string $username, string $password): bool
    {
        return $this->mockSsh2Result ?? false;
    }

    protected function authenticateWithSshpass(string $sshpassPath, string $username, string $password): bool
    {
        return $this->mockSshpassResult ?? false;
    }
}

/**
 * @internal
 */
final class SystemAuthenticatorTest extends CIUnitTestCase
{
    public function testEmptyCredentialsReturnFalse(): void
    {
        $auth = new SystemAuthenticator();
        $this->assertFalse($auth->authenticate('', ''));
        $this->assertFalse($auth->authenticate('user', ''));
        $this->assertFalse($auth->authenticate('', 'password'));
    }

    public function testUsernameStartingWithHyphenIsRejected(): void
    {
        $auth = new SystemAuthenticator();
        $this->assertFalse($auth->authenticate('-oProxyCommand=touch/tmp/pwned', 'password'));
        $this->assertFalse($auth->authenticate('--some-option', 'password'));
    }


    public function testAuthenticateWithPwauthSuccess(): void
    {
        $auth = new MockSystemAuthenticator();
        $auth->mockPwauthPath = '/usr/bin/pwauth';
        $auth->mockPwauthResult = true;

        $this->assertTrue($auth->authenticate('valid_user', 'correct_password'));
    }

    public function testAuthenticateWithPwauthFailure(): void
    {
        $auth = new MockSystemAuthenticator();
        $auth->mockPwauthPath = '/usr/bin/pwauth';
        $auth->mockPwauthResult = false;

        $this->assertFalse($auth->authenticate('invalid_user', 'wrong_password'));
    }

    public function testAuthenticateSsh2FallbackSuccess(): void
    {
        $auth = new MockSystemAuthenticator();
        $auth->mockPwauthPath = null;
        $auth->mockSsh2Loaded = true;
        $auth->mockSsh2Result = true;

        $this->assertTrue($auth->authenticate('ssh_user', 'ssh_pass'));
    }

    public function testAuthenticateSshpassFallbackSuccess(): void
    {
        $auth = new MockSystemAuthenticator();
        $auth->mockPwauthPath = null;
        $auth->mockSsh2Loaded = false;
        $auth->mockSshpassPath = '/usr/bin/sshpass';
        $auth->mockSshpassResult = true;

        $this->assertTrue($auth->authenticate('sshpass_user', 'sshpass_pass'));
    }

    public function testAuthenticateFailureAllOptions(): void
    {
        $auth = new MockSystemAuthenticator();
        $auth->mockPwauthPath = null;
        $auth->mockSsh2Loaded = false;
        $auth->mockSshpassPath = null;

        $this->assertFalse($auth->authenticate('any_user', 'any_pass'));
    }
}
