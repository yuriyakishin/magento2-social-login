<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Block\Social;

use Magento\Framework\View\Element\Template;
use Yu\SocialLogin\Model\Config;

class Button extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Provider code passed via the "provider_code" layout argument.
     *
     * @return string
     */
    public function getProviderCode(): string
    {
        return (string)$this->getData('provider_code');
    }

    /**
     * URL that starts the OAuth flow for this provider.
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        return $this->getUrl('yu_social_login/social/auth', ['provider' => $this->getProviderCode()]);
    }

    /**
     * Human-readable provider name for the button text.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->config->getProviderLabel($this->getProviderCode());
    }

    /**
     * Render nothing when the provider is disabled or not fully configured.
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->config->isProviderEnabled($this->getProviderCode())) {
            return '';
        }

        return parent::_toHtml();
    }
}
