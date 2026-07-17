<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Test\Unit\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Yu\SocialLogin\Exception\AlreadyLinkedException;
use Yu\SocialLogin\Exception\EmailInUseException;
use Yu\SocialLogin\Exception\EmailRequiredException;
use Yu\SocialLogin\Model\Config;
use Yu\SocialLogin\Model\Data\SocialProfile;
use Yu\SocialLogin\Model\ResourceModel\SocialAccount as SocialAccountResource;
use Yu\SocialLogin\Model\ResourceModel\SocialAccount\Collection;
use Yu\SocialLogin\Model\ResourceModel\SocialAccount\CollectionFactory;
use Yu\SocialLogin\Model\SocialAccount;
use Yu\SocialLogin\Model\SocialAccountFactory;
use Yu\SocialLogin\Model\SocialLogin;

class SocialLoginTest extends TestCase
{
    private CollectionFactory|MockObject $collectionFactory;
    private SocialAccountFactory|MockObject $socialAccountFactory;
    private SocialAccountResource|MockObject $resource;
    private CustomerRepositoryInterface|MockObject $customerRepository;
    private AccountManagementInterface|MockObject $accountManagement;
    private SocialLogin $service;

    /**
     * Build the service with all collaborators mocked.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->socialAccountFactory = $this->createMock(SocialAccountFactory::class);
        $this->resource = $this->createMock(SocialAccountResource::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerFactory = $this->createMock(CustomerInterfaceFactory::class);
        $customerFactory->method('create')
            ->willReturnCallback(fn() => $this->createMock(CustomerInterface::class));
        $this->accountManagement = $this->createMock(AccountManagementInterface::class);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $config = $this->createMock(Config::class);
        $config->method('getProviderLabel')->willReturnCallback(fn(string $code) => ucfirst($code));

        $this->service = new SocialLogin(
            $this->collectionFactory,
            $this->socialAccountFactory,
            $this->resource,
            $this->customerRepository,
            $customerFactory,
            $this->accountManagement,
            $storeManager,
            $config
        );
    }

    /**
     * Collection whose getFirstItem() returns $found (or an empty SocialAccount).
     */
    private function mockCollection(?SocialAccount $found): Collection|MockObject
    {
        if ($found === null) {
            $found = $this->createMock(SocialAccount::class);
            $found->method('getId')->willReturn(null);
        }
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($found);

        return $collection;
    }

    /**
     * Mock of an existing link row.
     *
     * @param int $entityId Row id (non-zero means "found")
     * @param int $customerId Customer the row belongs to
     * @return SocialAccount|MockObject
     */
    private function mockAccount(int $entityId, int $customerId): SocialAccount|MockObject
    {
        $account = $this->createMock(SocialAccount::class);
        $account->method('getId')->willReturn($entityId);
        $account->method('getData')->with('customer_id')->willReturn($customerId);

        return $account;
    }

    /**
     * Sample Google profile.
     *
     * @param string|null $email null mimics a provider that shared no email
     * @return SocialProfile
     */
    private function profile(?string $email = 'user@example.com'): SocialProfile
    {
        return new SocialProfile('google', 'g-123', $email, 'John', 'Doe');
    }

    /**
     * Resolve returns customer found by social id.
     *
     * @return void
     */
    public function testResolveReturnsCustomerFoundBySocialId(): void
    {
        $this->collectionFactory->method('create')
            ->willReturn($this->mockCollection($this->mockAccount(11, 7)));
        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->expects($this->once())
            ->method('getById')->with(7)->willReturn($customer);
        $this->resource->expects($this->never())->method('save');

        $this->assertSame($customer, $this->service->resolve($this->profile()));
    }

    /**
     * Resolve throws when provider gave no email.
     *
     * @return void
     */
    public function testResolveThrowsWhenProviderGaveNoEmail(): void
    {
        $this->collectionFactory->method('create')
            ->willReturn($this->mockCollection(null));

        $this->expectException(EmailRequiredException::class);
        $this->service->resolve($this->profile(null));
    }

    /**
     * Resolve links existing customer by email.
     *
     * @return void
     */
    public function testResolveLinksExistingCustomerByEmail(): void
    {
        $this->collectionFactory->method('create')
            ->willReturnCallback(fn() => $this->mockCollection(null));
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $this->customerRepository->method('get')
            ->with('user@example.com', 1)->willReturn($customer);
        $newAccount = $this->createMock(SocialAccount::class);
        $newAccount->expects($this->once())->method('setData')->with([
            'customer_id' => 7,
            'provider' => 'google',
            'social_id' => 'g-123',
            'email' => 'user@example.com',
        ]);
        $this->socialAccountFactory->method('create')->willReturn($newAccount);
        $this->resource->expects($this->once())->method('save')->with($newAccount);

        $this->assertSame($customer, $this->service->resolve($this->profile()));
    }

    /**
     * Resolve creates customer when email unknown.
     *
     * @return void
     */
    public function testResolveCreatesCustomerWhenEmailUnknown(): void
    {
        $this->collectionFactory->method('create')
            ->willReturnCallback(fn() => $this->mockCollection(null));
        $this->customerRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('no customer')));
        $created = $this->createMock(CustomerInterface::class);
        $created->method('getId')->willReturn(9);
        $this->accountManagement->expects($this->once())
            ->method('createAccount')->willReturn($created);
        $newAccount = $this->createMock(SocialAccount::class);
        $this->socialAccountFactory->method('create')->willReturn($newAccount);
        $this->resource->expects($this->once())->method('save')->with($newAccount);

        $this->assertSame($created, $this->service->resolve($this->profile()));
    }

    /**
     * Register with email rejects taken email.
     *
     * @return void
     */
    public function testRegisterWithEmailRejectsTakenEmail(): void
    {
        $this->customerRepository->method('get')
            ->willReturn($this->createMock(CustomerInterface::class));
        $this->accountManagement->expects($this->never())->method('createAccount');

        $this->expectException(EmailInUseException::class);
        $this->service->registerWithEmail($this->profile(null), 'taken@example.com');
    }

    /**
     * Link rejects social id owned by another customer.
     *
     * @return void
     */
    public function testLinkRejectsSocialIdOwnedByAnotherCustomer(): void
    {
        $this->collectionFactory->method('create')
            ->willReturn($this->mockCollection($this->mockAccount(11, 5)));

        $this->expectException(AlreadyLinkedException::class);
        $this->service->link(7, $this->profile());
    }

    /**
     * Link is no op when already linked to same customer.
     *
     * @return void
     */
    public function testLinkIsNoOpWhenAlreadyLinkedToSameCustomer(): void
    {
        $this->collectionFactory->method('create')
            ->willReturn($this->mockCollection($this->mockAccount(11, 7)));
        $this->resource->expects($this->never())->method('save');

        $this->service->link(7, $this->profile());
    }

    /**
     * Unlink deletes account.
     *
     * @return void
     */
    public function testUnlinkDeletesAccount(): void
    {
        $account = $this->mockAccount(11, 7);
        $this->collectionFactory->method('create')
            ->willReturn($this->mockCollection($account));
        $this->resource->expects($this->once())->method('delete')->with($account);

        $this->assertTrue($this->service->unlink(7, 'google'));
    }

    /**
     * Unlink returns false when nothing linked.
     *
     * @return void
     */
    public function testUnlinkReturnsFalseWhenNothingLinked(): void
    {
        $this->collectionFactory->method('create')
            ->willReturn($this->mockCollection(null));
        $this->resource->expects($this->never())->method('delete');

        $this->assertFalse($this->service->unlink(7, 'google'));
    }

    /**
     * Link rejects second account of same provider.
     *
     * @return void
     */
    public function testLinkRejectsSecondAccountOfSameProvider(): void
    {
        $this->collectionFactory->method('create')
            ->willReturnOnConsecutiveCalls(
                $this->mockCollection(null),
                $this->mockCollection($this->mockAccount(11, 7))
            );
        $this->resource->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Another Google account is already linked to your customer account.');
        $this->service->link(7, $this->profile());
    }

    /**
     * Register with email creates and links customer.
     *
     * @return void
     */
    public function testRegisterWithEmailCreatesAndLinksCustomer(): void
    {
        $this->customerRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('no customer')));
        $created = $this->createMock(CustomerInterface::class);
        $created->method('getId')->willReturn(9);
        $this->accountManagement->expects($this->once())
            ->method('createAccount')->willReturn($created);
        $this->collectionFactory->method('create')
            ->willReturnCallback(fn() => $this->mockCollection(null));
        $newAccount = $this->createMock(SocialAccount::class);
        $newAccount->expects($this->once())->method('setData')->with([
            'customer_id' => 9,
            'provider' => 'google',
            'social_id' => 'g-123',
            'email' => null,
        ]);
        $this->socialAccountFactory->method('create')->willReturn($newAccount);
        $this->resource->expects($this->once())->method('save')->with($newAccount);

        $this->assertSame(
            $created,
            $this->service->registerWithEmail($this->profile(null), 'new@example.com')
        );
    }

    /**
     * Get linked accounts returns accounts.
     *
     * @return void
     */
    public function testGetLinkedAccountsReturnsAccounts(): void
    {
        $accountMock = $this->mockAccount(11, 7);
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getItems')->willReturn(['k' => $accountMock]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertSame([$accountMock], $this->service->getLinkedAccounts(7));
    }
}
