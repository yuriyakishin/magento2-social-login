<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Block\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Yu\SocialLogin\Model\Config;
use Yu\SocialLogin\Model\SocialLogin;

class SocialAccounts extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Config $config,
        private readonly SocialLogin $socialLogin,
        private readonly CustomerSession $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * One row per enabled provider; links to providers that have been
     * disabled since are shown too, so they can still be unlinked.
     *
     * @return array<string, array{label: string, account: ?\Yu\SocialLogin\Model\SocialAccount, link_url: string}>
     */
    public function getRows(): array
    {
        $linked = [];
        foreach ($this->socialLogin->getLinkedAccounts((int)$this->customerSession->getCustomerId()) as $account) {
            $linked[(string)$account->getData('provider')] = $account;
        }
        $codes = array_unique(array_merge($this->config->getEnabledProviders(), array_keys($linked)));
        $rows = [];
        foreach ($codes as $code) {
            $rows[$code] = [
                'label' => $this->config->getProviderLabel($code),
                'account' => $linked[$code] ?? null,
                'link_url' => $this->getUrl('yu_social_login/social/auth', ['provider' => $code]),
            ];
        }

        return $rows;
    }

    /**
     * Target URL of the unlink POST form.
     *
     * @return string
     */
    public function getUnlinkUrl(): string
    {
        return $this->getUrl('yu_social_login/account/unlink');
    }
}
