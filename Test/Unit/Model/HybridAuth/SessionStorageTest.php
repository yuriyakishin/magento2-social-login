<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Test\Unit\Model\HybridAuth;

use Magento\Framework\Session\Generic;
use PHPUnit\Framework\TestCase;
use Yu\SocialLogin\Model\HybridAuth\SessionStorage;

class SessionStorageTest extends TestCase
{
    private array $data = [];
    private SessionStorage $storage;

    /**
     * Build the storage over a session mock backed by a plain array.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->data = [];
        $session = $this->getMockBuilder(Generic::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->addMethods(['setData', 'unsetData'])
            ->getMock();
        $session->method('setData')->willReturnCallback(function ($key, $value) {
            $this->data[$key] = $value;
        });
        $session->method('getData')->willReturnCallback(function ($key = '') {
            return $key === '' ? $this->data : ($this->data[$key] ?? null);
        });
        $session->method('unsetData')->willReturnCallback(function ($key) {
            unset($this->data[$key]);
        });
        $this->storage = new SessionStorage($session);
    }

    /**
     * Set get delete.
     *
     * @return void
     */
    public function testSetGetDelete(): void
    {
        $this->storage->set('hauth_session.google.token', 'abc');
        $this->assertSame('abc', $this->storage->get('hauth_session.google.token'));

        $this->storage->delete('hauth_session.google.token');
        $this->assertNull($this->storage->get('hauth_session.google.token'));
    }

    /**
     * Delete match removes by prefix.
     *
     * @return void
     */
    public function testDeleteMatchRemovesByPrefix(): void
    {
        $this->storage->set('hauth_session.google.token', 'a');
        $this->storage->set('hauth_session.google.state', 'b');
        $this->storage->set('hauth_session.facebook.token', 'c');

        $this->storage->deleteMatch('hauth_session.google.');

        $this->assertNull($this->storage->get('hauth_session.google.token'));
        $this->assertNull($this->storage->get('hauth_session.google.state'));
        $this->assertSame('c', $this->storage->get('hauth_session.facebook.token'));
    }

    /**
     * Clear removes only own keys.
     *
     * @return void
     */
    public function testClearRemovesOnlyOwnKeys(): void
    {
        $this->data['unrelated'] = 'keep';
        $this->storage->set('hauth_session.google.token', 'a');

        $this->storage->clear();

        $this->assertSame('keep', $this->data['unrelated']);
        $this->assertNull($this->storage->get('hauth_session.google.token'));
    }
}
