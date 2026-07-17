<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SocialAccount extends AbstractDb
{
    /**
     * Initialize the main table and its primary key.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('yu_social_login', 'entity_id');
    }
}
