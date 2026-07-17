<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Yu\SocialLogin\Model\Config;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;
    private Config $config;

    /**
     * Build the config with mocked scope config and encryptor.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->config = new Config($this->scopeConfig, $this->encryptor);
    }

    /**
     * Stub isSetFlag() answers.
     *
     * @param array<string, bool> $map Config path => flag value
     * @return void
     */
    private function givenFlags(array $map): void
    {
        $rows = [];
        foreach ($map as $path => $value) {
            $rows[] = [$path, ScopeInterface::SCOPE_STORE, null, $value];
        }
        $this->scopeConfig->method('isSetFlag')->willReturnMap($rows);
    }

    /**
     * Stub getValue() answers.
     *
     * @param array<string, mixed> $map Config path => value
     * @return void
     */
    private function givenValues(array $map): void
    {
        $rows = [];
        foreach ($map as $path => $value) {
            $rows[] = [$path, ScopeInterface::SCOPE_STORE, null, $value];
        }
        $this->scopeConfig->method('getValue')->willReturnMap($rows);
    }

    /**
     * Provider enabled when fully configured.
     *
     * @return void
     */
    public function testProviderEnabledWhenFullyConfigured(): void
    {
        $this->givenFlags([
            'yu_social_login/general/enabled' => true,
            'yu_social_login/google/enabled' => true,
        ]);
        $this->givenValues([
            'yu_social_login/google/required_fields' => 'client_id,client_secret',
            'yu_social_login/google/encrypted_fields' => 'client_secret',
            'yu_social_login/google/client_id' => 'id123',
            'yu_social_login/google/client_secret' => 'encrypted',
        ]);
        $this->encryptor->method('decrypt')->with('encrypted')->willReturn('secret123');

        $this->assertTrue($this->config->isProviderEnabled('google'));
    }

    /**
     * Provider disabled without credentials.
     *
     * @return void
     */
    public function testProviderDisabledWithoutCredentials(): void
    {
        $this->givenFlags([
            'yu_social_login/general/enabled' => true,
            'yu_social_login/google/enabled' => true,
        ]);
        $this->givenValues([
            'yu_social_login/google/required_fields' => 'client_id,client_secret',
            'yu_social_login/google/client_id' => '',
            'yu_social_login/google/client_secret' => '',
        ]);

        $this->assertFalse($this->config->isProviderEnabled('google'));
    }

    /**
     * Provider disabled when module disabled.
     *
     * @return void
     */
    public function testProviderDisabledWhenModuleDisabled(): void
    {
        $this->givenFlags([
            'yu_social_login/general/enabled' => false,
            'yu_social_login/google/enabled' => true,
        ]);

        $this->assertFalse($this->config->isProviderEnabled('google'));
    }

    /**
     * Get client secret decrypts.
     *
     * @return void
     */
    public function testGetClientSecretDecrypts(): void
    {
        $this->givenValues([
            'yu_social_login/google/encrypted_fields' => 'client_secret',
            'yu_social_login/google/client_secret' => 'encrypted',
        ]);
        $this->encryptor->method('decrypt')->with('encrypted')->willReturn('plain');

        $this->assertSame('plain', $this->config->getClientSecret('google'));
    }

    /**
     * Apple disabled without signing keys.
     *
     * @return void
     */
    public function testAppleDisabledWithoutSigningKeys(): void
    {
        $this->givenFlags([
            'yu_social_login/general/enabled' => true,
            'yu_social_login/apple/enabled' => true,
        ]);
        $this->givenValues([
            'yu_social_login/apple/required_fields' => 'client_id,team_id,key_id,key_content',
            'yu_social_login/apple/client_id' => 'ua.net.yu.signin',
            'yu_social_login/apple/team_id' => 'TEAM123',
            'yu_social_login/apple/key_id' => 'KEY123',
            'yu_social_login/apple/key_content' => '',
        ]);

        $this->assertFalse($this->config->isProviderEnabled('apple'));
    }

    /**
     * Apple enabled when fully configured.
     *
     * @return void
     */
    public function testAppleEnabledWhenFullyConfigured(): void
    {
        $this->givenFlags([
            'yu_social_login/general/enabled' => true,
            'yu_social_login/apple/enabled' => true,
        ]);
        $this->givenValues([
            'yu_social_login/apple/required_fields' => 'client_id,team_id,key_id,key_content',
            'yu_social_login/apple/encrypted_fields' => 'key_content',
            'yu_social_login/apple/client_id' => 'ua.net.yu.signin',
            'yu_social_login/apple/team_id' => 'TEAM123',
            'yu_social_login/apple/key_id' => 'KEY123',
            'yu_social_login/apple/key_content' => 'encrypted-pem',
        ]);
        $this->encryptor->method('decrypt')->with('encrypted-pem')->willReturn('-----BEGIN PRIVATE KEY-----');

        $this->assertTrue($this->config->isProviderEnabled('apple'));
    }

    /**
     * Get provider value decrypts encrypted fields.
     *
     * @return void
     */
    public function testGetProviderValueDecryptsEncryptedFields(): void
    {
        $this->givenValues([
            'yu_social_login/apple/encrypted_fields' => 'key_content',
            'yu_social_login/apple/key_content' => 'encrypted-pem',
            'yu_social_login/apple/team_id' => 'TEAM123',
        ]);
        $this->encryptor->method('decrypt')->with('encrypted-pem')->willReturn('pem-plain');

        $this->assertSame('pem-plain', $this->config->getProviderValue('apple', 'key_content'));
        $this->assertSame('TEAM123', $this->config->getProviderValue('apple', 'team_id'));
    }

    /**
     * Label comes from config.xml; without one the code is capitalized.
     *
     * @return void
     */
    public function testGetProviderLabel(): void
    {
        $this->givenValues([
            'yu_social_login/github/label' => 'GitHub',
            'yu_social_login/x_twitter/label' => 'X',
        ]);

        $this->assertSame('GitHub', $this->config->getProviderLabel('github'));
        $this->assertSame('X', $this->config->getProviderLabel('x_twitter'));
        $this->assertSame('Someday', $this->config->getProviderLabel('someday'));
    }

    /**
     * Required fields are parsed from the comma-separated config node;
     * a provider without the node declares no fields at all.
     *
     * @return void
     */
    public function testGetProviderRequiredFields(): void
    {
        $this->givenValues([
            'yu_social_login/apple/required_fields' => 'client_id, team_id,key_id , key_content',
        ]);

        $this->assertSame(
            ['client_id', 'team_id', 'key_id', 'key_content'],
            $this->config->getProviderRequiredFields('apple')
        );
        $this->assertSame([], $this->config->getProviderRequiredFields('someday'));
    }

    /**
     * Encrypted fields are parsed from the comma-separated config node;
     * a provider without the node gets no decryption at all.
     *
     * @return void
     */
    public function testGetProviderEncryptedFields(): void
    {
        $this->givenValues([
            'yu_social_login/apple/encrypted_fields' => 'key_content',
        ]);

        $this->assertSame(['key_content'], $this->config->getProviderEncryptedFields('apple'));
        $this->assertSame([], $this->config->getProviderEncryptedFields('someday'));
    }

    /**
     * Adapter class comes from config.xml; empty for undeclared providers.
     *
     * @return void
     */
    public function testGetProviderAdapterClass(): void
    {
        $this->givenValues([
            'yu_social_login/google/adapter' => 'Hybridauth\Provider\Google',
        ]);

        $this->assertSame('Hybridauth\Provider\Google', $this->config->getProviderAdapterClass('google'));
        $this->assertSame('', $this->config->getProviderAdapterClass('someday'));
    }

    /**
     * Get provider sort order.
     *
     * @return void
     */
    public function testGetProviderSortOrder(): void
    {
        $this->givenValues([
            'yu_social_login/google/sort_order' => '30',
            'yu_social_login/apple/sort_order' => '',
        ]);

        $this->assertSame(30, $this->config->getProviderSortOrder('google'));
        $this->assertSame(0, $this->config->getProviderSortOrder('apple'));
    }

    /**
     * Get enabled providers filters section.
     *
     * @return void
     */
    public function testGetEnabledProvidersFiltersSection(): void
    {
        $this->givenFlags([
            'yu_social_login/general/enabled' => true,
            'yu_social_login/google/enabled' => true,
            'yu_social_login/facebook/enabled' => false,
        ]);
        $this->givenValues([
            'yu_social_login' => [
                'general' => ['enabled' => '1'],
                'google' => ['enabled' => '1', 'client_id' => 'id', 'client_secret' => 'enc'],
                'facebook' => ['enabled' => '0'],
            ],
            'yu_social_login/google/client_id' => 'id',
            'yu_social_login/google/client_secret' => 'enc',
        ]);
        $this->encryptor->method('decrypt')->willReturn('plain');

        $this->assertSame(['google'], $this->config->getEnabledProviders());
    }
}
