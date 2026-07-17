<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Model;

use Magento\Framework\Model\AbstractModel;
use Yu\SocialLogin\Model\ResourceModel\SocialAccount as SocialAccountResource;

class SocialAccount extends AbstractModel
{
    /**
     * Initialize the model with its resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(SocialAccountResource::class);
    }
}
