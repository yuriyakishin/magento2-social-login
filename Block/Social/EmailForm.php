<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Block\Social;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Yu\SocialLogin\Model\Config;

class EmailForm extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly CustomerSession $customerSession,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Name of the provider the pending profile came from, for the form heading.
     *
     * @return string
     */
    public function getProviderLabel(): string
    {
        $pending = (array)$this->customerSession->getYuSocialPendingProfile();

        return $this->config->getProviderLabel((string)($pending['provider'] ?? ''));
    }

    /**
     * Target URL of the email POST form.
     *
     * @return string
     */
    public function getFormAction(): string
    {
        return $this->getUrl('yu_social_login/social/emailpost');
    }
}
