<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Model\HybridAuth;

use Hybridauth\Storage\StorageInterface;
use Magento\Framework\Session\Generic;

/**
 * Hybridauth storage on top of the Magento session: the library must not
 * work with $_SESSION directly, bypassing Magento's session handling.
 */
class SessionStorage implements StorageInterface
{
    private const KEY_PREFIX = 'yu_social_login_';

    public function __construct(
        private readonly Generic $session
    ) {
    }

    /**
     * Retrieve an item.
     *
     * @param string $key
     * @return mixed null when the key is not set
     */
    public function get($key)
    {
        return $this->session->getData($this->normalize($key));
    }

    /**
     * Store an item.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set($key, $value)
    {
        $this->session->setData($this->normalize($key), $value);
    }

    /**
     * Remove a single item.
     *
     * @param string $key
     * @return void
     */
    public function delete($key)
    {
        $this->session->unsetData($this->normalize($key));
    }

    /**
     * Remove every item whose key starts with the given prefix.
     *
     * @param string $key Key prefix
     * @return void
     */
    public function deleteMatch($key)
    {
        $prefix = $this->normalize($key);
        foreach (array_keys((array)$this->session->getData()) as $dataKey) {
            if (str_starts_with((string)$dataKey, $prefix)) {
                $this->session->unsetData((string)$dataKey);
            }
        }
    }

    /**
     * Remove all hybridauth items, leaving other Magento session data untouched.
     *
     * @return void
     */
    public function clear()
    {
        foreach (array_keys((array)$this->session->getData()) as $dataKey) {
            if (str_starts_with((string)$dataKey, self::KEY_PREFIX)) {
                $this->session->unsetData((string)$dataKey);
            }
        }
    }

    /**
     * Map a hybridauth key to a safe, prefixed Magento session key.
     *
     * @param string $key
     * @return string
     */
    private function normalize($key): string
    {
        return self::KEY_PREFIX . strtolower(str_replace('.', '_', (string)$key));
    }
}
