<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_SECTION = 'yu_social_login';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Whether social login is enabled globally (the master switch).
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SECTION . '/general/enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * A provider is available when the master switch, its own switch and all
     * of its required credential fields are in place.
     *
     * @param string $provider Provider code, e.g. "google"
     * @return bool
     */
    public function isProviderEnabled(string $provider): bool
    {
        if (!$this->isEnabled()
            || !$this->scopeConfig->isSetFlag(
                self::XML_PATH_SECTION . '/' . $provider . '/enabled',
                ScopeInterface::SCOPE_STORE
            )
        ) {
            return false;
        }
        foreach ($this->getProviderRequiredFields($provider) as $field) {
            if ($this->getProviderValue($provider, $field) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Read a provider config field, transparently decrypting encrypted ones.
     *
     * @param string $provider Provider code, e.g. "google"
     * @param string $field Field id within the provider group, e.g. "client_id"
     * @return string Empty string when the field is not set
     */
    public function getProviderValue(string $provider, string $field): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SECTION . '/' . $provider . '/' . $field,
            ScopeInterface::SCOPE_STORE
        );
        if ($value !== '' && in_array($field, $this->getProviderEncryptedFields($provider), true)) {
            $value = (string)$this->encryptor->decrypt($value);
        }

        return $value;
    }

    /**
     * OAuth client id (App ID / Application ID) of the provider.
     *
     * @param string $provider Provider code
     * @return string
     */
    public function getClientId(string $provider): string
    {
        return $this->getProviderValue($provider, 'client_id');
    }

    /**
     * OAuth client secret of the provider, decrypted.
     *
     * @param string $provider Provider code
     * @return string
     */
    public function getClientSecret(string $provider): string
    {
        return $this->getProviderValue($provider, 'client_secret');
    }

    /**
     * Human-readable provider name shown on buttons and in messages.
     * Declared as the "label" node of the provider's config.xml group;
     * without one the code is capitalized as a fallback.
     *
     * @param string $provider Provider code
     * @return string
     */
    public function getProviderLabel(string $provider): string
    {
        $label = $this->getProviderValue($provider, 'label');

        return $label !== '' ? $label : ucfirst($provider);
    }

    /**
     * Hybridauth adapter class declared as the "adapter" node of the
     * provider's config.xml group.
     *
     * @param string $provider Provider code
     * @return string Empty string when the provider declares no adapter
     */
    public function getProviderAdapterClass(string $provider): string
    {
        return $this->getProviderValue($provider, 'adapter');
    }

    /**
     * Credential fields that must be filled for the provider to work,
     * declared comma-separated in the "required_fields" node of the
     * provider's config.xml group. There is deliberately no fallback: a
     * provider without the node gets no credential check at all, so every
     * provider must declare its own list.
     *
     * @param string $provider Provider code
     * @return string[]
     */
    public function getProviderRequiredFields(string $provider): array
    {
        return $this->getProviderFieldList($provider, 'required_fields');
    }

    /**
     * Fields stored encrypted (backed by Backend\Encrypted in system.xml),
     * declared comma-separated in the "encrypted_fields" node of the
     * provider's config.xml group. Must match the system.xml backend models:
     * decrypting a plain value, or skipping an encrypted one, both break the
     * provider silently. No fallback — see getProviderRequiredFields().
     *
     * @param string $provider Provider code
     * @return string[]
     */
    public function getProviderEncryptedFields(string $provider): array
    {
        return $this->getProviderFieldList($provider, 'encrypted_fields');
    }

    /**
     * Parse a comma-separated field-list node of a provider group. Reads the
     * scope config directly: going through getProviderValue() would recurse,
     * because that method itself asks for the encrypted-fields list.
     *
     * @param string $provider Provider code
     * @param string $node Node name within the provider group
     * @return string[] Empty when the node is absent or empty
     */
    private function getProviderFieldList(string $provider, string $node): array
    {
        $fields = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SECTION . '/' . $provider . '/' . $node,
            ScopeInterface::SCOPE_STORE
        );

        return array_values(array_filter(array_map('trim', explode(',', $fields))));
    }

    /**
     * Button position on the login/registration pages: lower values render first.
     *
     * @param string $provider Provider code
     * @return int
     */
    public function getProviderSortOrder(string $provider): int
    {
        return (int)$this->getProviderValue($provider, 'sort_order');
    }

    /**
     * Codes of all providers that are currently available for sign-in.
     *
     * @return string[]
     */
    public function getEnabledProviders(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }
        $section = $this->scopeConfig->getValue(self::XML_PATH_SECTION, ScopeInterface::SCOPE_STORE) ?: [];
        $providers = [];
        foreach (array_keys((array)$section) as $group) {
            if ($group !== 'general' && $this->isProviderEnabled((string)$group)) {
                $providers[] = (string)$group;
            }
        }

        return $providers;
    }
}
