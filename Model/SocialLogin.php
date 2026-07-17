<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Yu\SocialLogin\Exception\AlreadyLinkedException;
use Yu\SocialLogin\Exception\EmailInUseException;
use Yu\SocialLogin\Exception\EmailRequiredException;
use Yu\SocialLogin\Model\Data\SocialProfile;
use Yu\SocialLogin\Model\ResourceModel\SocialAccount as SocialAccountResource;
use Yu\SocialLogin\Model\ResourceModel\SocialAccount\CollectionFactory;

class SocialLogin
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SocialAccountFactory $socialAccountFactory,
        private readonly SocialAccountResource $socialAccountResource,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly AccountManagementInterface $accountManagement,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * Guest sign-in: find by social_id, then by email, otherwise create a customer.
     *
     * @param SocialProfile $profile Profile returned by the provider
     * @return CustomerInterface Customer to log in
     * @throws EmailRequiredException the provider did not share an email
     * @throws AlreadyLinkedException social_id is already linked to another customer (via link())
     * @throws LocalizedException another account of this provider is already linked (via link())
     */
    public function resolve(SocialProfile $profile): CustomerInterface
    {
        $account = $this->findAccount($profile->getProvider(), $profile->getSocialId());
        if ($account) {
            return $this->customerRepository->getById((int)$account->getData('customer_id'));
        }
        $email = (string)$profile->getEmail();
        if ($email === '') {
            throw new EmailRequiredException();
        }
        try {
            // The provider has verified this email on its side, so auto-linking is safe.
            $customer = $this->customerRepository->get($email, $this->getWebsiteId());
        } catch (NoSuchEntityException $e) {
            $customer = $this->createCustomer($profile, $email);
        }
        $this->link((int)$customer->getId(), $profile);

        return $customer;
    }

    /**
     * Finish registration with an email typed into the manual form. Nobody has
     * verified such an email, so linking it to an existing account is forbidden.
     *
     * @param SocialProfile $profile Profile stashed in the session by the callback
     * @param string $email Email the customer typed into the form
     * @return CustomerInterface Newly created customer
     * @throws EmailInUseException
     * @throws AlreadyLinkedException social_id is already linked to another customer (via link())
     * @throws LocalizedException another account of this provider is already linked (via link())
     */
    public function registerWithEmail(SocialProfile $profile, string $email): CustomerInterface
    {
        $taken = true;
        try {
            $this->customerRepository->get($email, $this->getWebsiteId());
        } catch (NoSuchEntityException $e) {
            $taken = false;
        }
        if ($taken) {
            throw new EmailInUseException();
        }
        $customer = $this->createCustomer($profile, $email);
        $this->link((int)$customer->getId(), $profile);

        return $customer;
    }

    /**
     * Attach the social account to a customer; a repeated identical link is a no-op.
     *
     * @param int $customerId Customer entity id
     * @param SocialProfile $profile Profile returned by the provider
     * @return void
     * @throws AlreadyLinkedException social_id is already linked to another customer
     * @throws LocalizedException another account of this provider is already linked to this customer
     */
    public function link(int $customerId, SocialProfile $profile): void
    {
        $existing = $this->findAccount($profile->getProvider(), $profile->getSocialId());
        if ($existing) {
            if ((int)$existing->getData('customer_id') === $customerId) {
                return;
            }
            throw new AlreadyLinkedException();
        }
        if ($this->findAccountByCustomer($customerId, $profile->getProvider())) {
            throw new LocalizedException(
                __(
                    'Another %1 account is already linked to your customer account.',
                    $this->config->getProviderLabel($profile->getProvider())
                )
            );
        }
        $account = $this->socialAccountFactory->create();
        $account->setData([
            'customer_id' => $customerId,
            'provider' => $profile->getProvider(),
            'social_id' => $profile->getSocialId(),
            'email' => $profile->getEmail(),
        ]);
        $this->socialAccountResource->save($account);
    }

    /**
     * Remove the provider link from a customer.
     *
     * @param int $customerId Customer entity id
     * @param string $provider Provider code
     * @return bool false when there was nothing to unlink
     */
    public function unlink(int $customerId, string $provider): bool
    {
        $account = $this->findAccountByCustomer($customerId, $provider);
        if (!$account) {
            return false;
        }
        $this->socialAccountResource->delete($account);

        return true;
    }

    /**
     * All social accounts linked to a customer.
     *
     * @param int $customerId Customer entity id
     * @return SocialAccount[]
     */
    public function getLinkedAccounts(int $customerId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);

        return array_values($collection->getItems());
    }

    /**
     * Link row for a provider-side user id, if any.
     *
     * @param string $provider Provider code
     * @param string $socialId User id on the provider's side
     * @return SocialAccount|null
     */
    private function findAccount(string $provider, string $socialId): ?SocialAccount
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('provider', $provider)
            ->addFieldToFilter('social_id', $socialId);
        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }

    /**
     * Link row of the given provider on the given customer, if any.
     *
     * @param int $customerId Customer entity id
     * @param string $provider Provider code
     * @return SocialAccount|null
     */
    private function findAccountByCustomer(int $customerId, string $provider): ?SocialAccount
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('provider', $provider);
        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }

    /**
     * Create a customer from the social profile (random password, standard welcome email).
     *
     * @param SocialProfile $profile Profile returned by the provider
     * @param string $email Email to register the customer with
     * @return CustomerInterface
     */
    private function createCustomer(SocialProfile $profile, string $email): CustomerInterface
    {
        $store = $this->storeManager->getStore();
        $firstname = $profile->getFirstname() !== '' ? $profile->getFirstname() : 'Customer';
        $lastname = $profile->getLastname() !== '' ? $profile->getLastname() : $firstname;
        $customer = $this->customerFactory->create();
        $customer->setEmail($email);
        $customer->setFirstname($firstname);
        $customer->setLastname($lastname);
        $customer->setWebsiteId((int)$store->getWebsiteId());
        $customer->setStoreId((int)$store->getId());

        // Null password: AccountManagement generates a random one and sends the welcome email.
        return $this->accountManagement->createAccount($customer);
    }

    /**
     * Website of the current store — customer lookups are website-scoped.
     *
     * @return int
     */
    private function getWebsiteId(): int
    {
        return (int)$this->storeManager->getStore()->getWebsiteId();
    }
}
