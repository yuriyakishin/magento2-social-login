<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use Yu\SocialLogin\Model\Config;
use Yu\SocialLogin\Model\SocialLogin;

class Unlink implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CustomerSession $customerSession,
        private readonly SocialLogin $socialLogin,
        private readonly ManagerInterface $messageManager,
        private readonly Config $config
    ) {
    }

    /**
     * Unlink a provider from the logged-in customer (form-key protected POST).
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            return $resultRedirect->setPath('customer/account/login');
        }
        if (!$this->formKeyValidator->validate($this->request)) {
            return $resultRedirect->setPath('yu_social_login/account/index');
        }
        $provider = strtolower((string)$this->request->getParam('provider'));
        if ($this->socialLogin->unlink((int)$this->customerSession->getCustomerId(), $provider)) {
            $this->messageManager->addSuccessMessage(
                __('Your %1 account has been unlinked.', $this->config->getProviderLabel($provider))
            );
        } else {
            $this->messageManager->addNoticeMessage(__('Nothing to unlink.'));
        }

        return $resultRedirect->setPath('yu_social_login/account/index');
    }
}
