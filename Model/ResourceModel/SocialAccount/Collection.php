<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Model\ResourceModel\SocialAccount;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Yu\SocialLogin\Model\ResourceModel\SocialAccount as SocialAccountResource;
use Yu\SocialLogin\Model\SocialAccount;

class Collection extends AbstractCollection
{
    /**
     * Initialize the collection with its model and resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(SocialAccount::class, SocialAccountResource::class);
    }
}
