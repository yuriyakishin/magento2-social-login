<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Controller\Social;

use Hybridauth\HttpClient\Util;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use Yu\SocialLogin\Model\Config;
use Yu\SocialLogin\Model\HybridAuth;

class Auth implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly RedirectInterface $redirect,
        private readonly Config $config,
        private readonly HybridAuth $hybridAuth,
        private readonly CustomerSession $customerSession,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Start the OAuth flow: redirect the customer to the provider's consent screen.
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
        $this->customerSession->unsYuSocialPendingProfile();
        $this->customerSession->setYuSocialReturnUrl($this->redirect->getRefererUrl());
        try {
            $adapter = $this->hybridAuth->getAdapter($provider);
            // By default hybridauth calls header() + exit; capture the URL instead
            // so the redirect goes out as a regular Magento response.
            $authUrl = null;
            Util::setRedirectHandler(function (string $url) use (&$authUrl) {
                $authUrl = $url;
            });
            $adapter->authenticate();
            if ($authUrl !== null) {
                return $resultRedirect->setUrl($authUrl);
            }

            // A token from a previous run is still valid — go straight to the callback.
            return $resultRedirect->setPath('yu_social_login/social/callback', ['provider' => $provider]);
        } catch (\Throwable $e) {
            $this->logger->error('[Yu_SocialLogin] auth: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                __('Unable to sign in via %1. Please try again.', $this->config->getProviderLabel($provider))
            );

            return $resultRedirect->setPath('customer/account/login');
        }
    }
}
