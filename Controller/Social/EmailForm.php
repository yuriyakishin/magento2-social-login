<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Controller\Social;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class EmailForm implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly CustomerSession $customerSession
    ) {
    }

    /**
     * Render the "enter your email" form; without a pending profile in the
     * session there is nothing to complete, so redirect to the login page.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if (!$this->customerSession->getYuSocialPendingProfile()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Complete Registration'));

        return $page;
    }
}
