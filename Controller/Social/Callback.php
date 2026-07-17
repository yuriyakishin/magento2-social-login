<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Controller\Social;

use Hybridauth\HttpClient\Util;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Url\HostChecker;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Yu\SocialLogin\Exception\EmailRequiredException;
use Yu\SocialLogin\Model\Config;
use Yu\SocialLogin\Model\Data\SocialProfile;
use Yu\SocialLogin\Model\HybridAuth;
use Yu\SocialLogin\Model\SocialLogin;

class Callback implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly Config $config,
        private readonly HybridAuth $hybridAuth,
        private readonly SocialLogin $socialLogin,
        private readonly CustomerSession $customerSession,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger,
        private readonly HostChecker $hostChecker
    ) {
    }

    /**
     * Handle the provider's OAuth callback: link when a customer is logged in,
     * otherwise log in / register the customer resolved from the profile.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $provider = strtolower((string)$this->request->getParam('provider'));
        $resultRedirect = $this->resultRedirectFactory->create();
        if (!$this->config->isProviderEnabled($provider)) {
            $this->messageManager->addErrorMessage(__('Social login is not available.'));

            return $resultRedirect->setPath('customer/account/login');
        }
        try {
            $profile = $this->fetchProfile($provider);
            if ($this->customerSession->isLoggedIn()) {
                $this->socialLogin->link((int)$this->customerSession->getCustomerId(), $profile);
                $this->messageManager->addSuccessMessage(
                    __('Your %1 account has been linked.', $this->config->getProviderLabel($provider))
                );

                return $resultRedirect->setPath('yu_social_login/account/index');
            }
            try {
                $customer = $this->socialLogin->resolve($profile);
            } catch (EmailRequiredException $e) {
                $this->customerSession->setYuSocialPendingProfile($profile->toArray());

                return $resultRedirect->setPath('yu_social_login/social/emailform');
            }
            $this->customerSession->loginById((int)$customer->getId());

            return $resultRedirect->setUrl($this->getReturnUrl());
        } catch (LocalizedException $e) {
            $this->logger->warning('[Yu_SocialLogin] callback rejected: ' . $e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('[Yu_SocialLogin] callback: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                __('Unable to sign in via %1. Please try again.', $this->config->getProviderLabel($provider))
            );
        }

        return $resultRedirect->setPath(
            $this->customerSession->isLoggedIn() ? 'yu_social_login/account/index' : 'customer/account/login'
        );
    }

    /**
     * Complete the code-for-token exchange and read the user's profile.
     *
     * @param string $provider Provider code
     * @return SocialProfile
     * @throws LocalizedException when the provider returned no user id
     */
    private function fetchProfile(string $provider): SocialProfile
    {
        // Redirect handler here as well: if the state has expired, hybridauth must not call exit.
        Util::setRedirectHandler(static function (): void {
        });
        $adapter = $this->hybridAuth->getAdapter($provider);
        $adapter->authenticate();
        $userProfile = $adapter->getUserProfile();
        $adapter->disconnect();
        $socialId = (string)$userProfile->identifier;
        if ($socialId === '') {
            throw new LocalizedException(__('The provider did not return a user ID.'));
        }

        return new SocialProfile(
            $provider,
            $socialId,
            $userProfile->email ? (string)$userProfile->email : null,
            (string)$userProfile->firstName,
            (string)$userProfile->lastName
        );
    }

    /**
     * URL to land on after login: the page the flow started from, but only
     * when it belongs to this site — otherwise the account dashboard.
     *
     * @return string
     */
    private function getReturnUrl(): string
    {
        $url = (string)$this->customerSession->getYuSocialReturnUrl();
        $this->customerSession->unsYuSocialReturnUrl();
        if ($url === '' || !$this->hostChecker->isOwnOrigin($url)) {
            return $this->urlBuilder->getUrl('customer/account');
        }

        return $url;
    }
}
