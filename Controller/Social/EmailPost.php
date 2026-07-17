<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Controller\Social;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use Yu\SocialLogin\Exception\EmailInUseException;
use Yu\SocialLogin\Model\Data\SocialProfile;
use Yu\SocialLogin\Model\SocialLogin;

class EmailPost implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly SocialLogin $socialLogin,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Finish registration with the manually entered email and log the customer in.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $pending = $this->customerSession->getYuSocialPendingProfile();
        if (!$pending) {
            return $resultRedirect->setPath('customer/account/login');
        }
        $email = trim((string)$this->request->getParam('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->messageManager->addErrorMessage(__('Please enter a valid email address.'));

            return $resultRedirect->setPath('yu_social_login/social/emailform');
        }
        try {
            $customer = $this->socialLogin->registerWithEmail(SocialProfile::fromArray((array)$pending), $email);
        } catch (EmailInUseException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->customerSession->unsYuSocialPendingProfile();

            return $resultRedirect->setPath('customer/account/login');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $resultRedirect->setPath('yu_social_login/social/emailform');
        } catch (\Throwable $e) {
            $this->logger->error('[Yu_SocialLogin] emailpost: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Unable to complete registration. Please try again.'));

            return $resultRedirect->setPath('customer/account/login');
        }
        $this->customerSession->unsYuSocialPendingProfile();
        $this->customerSession->loginById((int)$customer->getId());
        $this->messageManager->addSuccessMessage(__('Your account has been created.'));

        return $resultRedirect->setPath('customer/account');
    }
}
