<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Model;

use Hybridauth\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Yu\SocialLogin\Model\HybridAuth\SessionStorage;

class HybridAuth
{
    public function __construct(
        private readonly Config $config,
        private readonly SessionStorage $storage,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Build a configured hybridauth adapter for an enabled provider.
     *
     * @param string $provider Provider code, e.g. "google"
     * @return AdapterInterface
     * @throws LocalizedException when the provider is disabled or unknown
     */
    public function getAdapter(string $provider): AdapterInterface
    {
        if (!$this->config->isProviderEnabled($provider)) {
            throw new LocalizedException(__('Social login via "%1" is not available.', $provider));
        }
        // The adapter class comes from the provider's config.xml node, so
        // third-party modules can register providers without touching this
        // module (adapter class names can't be derived from codes anyway:
        // "github" -> GitHub.php would miss on a case-sensitive filesystem).
        $class = $this->config->getProviderAdapterClass($provider);
        if ($class === '' || !class_exists($class)) {
            throw new LocalizedException(__('Unknown social login provider "%1".', $provider));
        }
        $config = [
            'callback' => $this->urlBuilder->getUrl(
                'yu_social_login/social/callback',
                ['provider' => $provider]
            ),
            'keys' => $this->buildKeys($provider),
        ];
        // Optional override via the "scope" node of the provider's config.xml
        // group; without it the adapter's default scope applies.
        $scope = $this->config->getProviderValue($provider, 'scope');
        if ($scope !== '') {
            $config['scope'] = $scope;
        }

        return new $class($config, null, $this->storage);
    }

    /**
     * Credentials in the shape hybridauth expects under the "keys" config entry.
     *
     * @param string $provider Provider code
     * @return array<string, string>
     */
    private function buildKeys(string $provider): array
    {
        if ($provider === 'apple') {
            // Apple has no static secret: the adapter signs a short-lived JWT
            // with the .p8 private key itself.
            return [
                'id' => $this->config->getClientId($provider),
                'team_id' => $this->config->getProviderValue($provider, 'team_id'),
                'key_id' => $this->config->getProviderValue($provider, 'key_id'),
                'key_file' => '',
                'key_content' => $this->config->getProviderValue($provider, 'key_content'),
            ];
        }

        return [
            'id' => $this->config->getClientId($provider),
            'secret' => $this->config->getClientSecret($provider),
        ];
    }
}
