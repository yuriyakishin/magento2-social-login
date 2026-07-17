<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly CustomerSession $customerSession
    ) {
    }

    /**
     * Render the "Social Accounts" page of the customer account area.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            // authenticate() remembers the target URL to return the customer here after login
            $this->customerSession->authenticate();

            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Social Accounts'));

        return $page;
    }
}
