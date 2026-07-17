<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Test\Unit\Model;

use Hybridauth\Provider\Amazon;
use Hybridauth\Provider\GitHub;
use Hybridauth\Provider\Google;
use Hybridauth\Provider\LinkedInOpenID;
use Hybridauth\Provider\MicrosoftGraph;
use Hybridauth\Provider\X;
use Hybridauth\Provider\Yahoo;
use Yu\SocialLogin\Model\HybridAuth\Provider\Apple;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;
use Yu\SocialLogin\Model\Config;
use Yu\SocialLogin\Model\HybridAuth;
use Yu\SocialLogin\Model\HybridAuth\SessionStorage;

class HybridAuthTest extends TestCase
{
    private Config $config;
    private HybridAuth $hybridAuth;

    /**
     * Build the factory with a mocked config, URL builder and session storage.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturn('https://yu.net.ua/social-login/social/callback/provider/google/');
        $storage = $this->createMock(SessionStorage::class);
        $this->hybridAuth = new HybridAuth($this->config, $storage, $url);
    }

    /**
     * @dataProvider providerAdapterClasses
     */
    public function testReturnsAdapterForProvider(string $code, string $class): void
    {
        $this->config->method('isProviderEnabled')->with($code)->willReturn(true);
        $this->config->method('getProviderAdapterClass')->with($code)->willReturn($class);
        $this->config->method('getClientId')->willReturn('id');
        $this->config->method('getClientSecret')->willReturn('secret');

        $this->assertInstanceOf($class, $this->hybridAuth->getAdapter($code));
    }

    /**
     * Provider code => expected hybridauth adapter class.
     *
     * @return array<string, array{string, string}>
     */
    public function providerAdapterClasses(): array
    {
        return [
            'google' => ['google', Google::class],
            'x_twitter' => ['x_twitter', X::class],
            'github' => ['github', GitHub::class],
            'amazon' => ['amazon', Amazon::class],
            'yahoo' => ['yahoo', Yahoo::class],
            'linkedin' => ['linkedin', LinkedInOpenID::class],
            'microsoft' => ['microsoft', MicrosoftGraph::class],
        ];
    }

    /**
     * Returns apple adapter.
     *
     * @return void
     */
    public function testReturnsAppleAdapter(): void
    {
        // The Apple adapter signs its JWT client secret on instantiation,
        // so the mocked config has to return a real EC (ES256) private key.
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        openssl_pkey_export($resource, $pem);

        $this->config->method('isProviderEnabled')->with('apple')->willReturn(true);
        $this->config->method('getProviderAdapterClass')->with('apple')->willReturn(Apple::class);
        $this->config->method('getClientId')->willReturn('ua.net.yu.signin');
        $this->config->method('getProviderValue')->willReturnMap([
            ['apple', 'team_id', 'TEAM123456'],
            ['apple', 'key_id', 'KEY1234567'],
            ['apple', 'key_content', $pem],
            ['apple', 'scope', ''],
        ]);

        $this->assertInstanceOf(Apple::class, $this->hybridAuth->getAdapter('apple'));
    }

    /**
     * Throws for disabled provider.
     *
     * @return void
     */
    public function testThrowsForDisabledProvider(): void
    {
        $this->config->method('isProviderEnabled')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->hybridAuth->getAdapter('google');
    }

    /**
     * Throws for unknown provider.
     *
     * @return void
     */
    public function testThrowsForUnknownProvider(): void
    {
        $this->config->method('isProviderEnabled')->willReturn(true);
        $this->config->method('getProviderAdapterClass')->with('nosuchprovider')->willReturn('');
        $this->config->method('getClientId')->willReturn('id');
        $this->config->method('getClientSecret')->willReturn('secret');

        $this->expectException(LocalizedException::class);
        $this->hybridAuth->getAdapter('nosuchprovider');
    }

    /**
     * A declared adapter class that does not exist is rejected, not fataled on.
     *
     * @return void
     */
    public function testThrowsForNonexistentAdapterClass(): void
    {
        $this->config->method('isProviderEnabled')->willReturn(true);
        $this->config->method('getProviderAdapterClass')->willReturn('No\Such\AdapterClass');

        $this->expectException(LocalizedException::class);
        $this->hybridAuth->getAdapter('broken');
    }
}
