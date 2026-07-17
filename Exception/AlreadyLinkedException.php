<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Exception;

use Magento\Framework\Exception\LocalizedException;

class AlreadyLinkedException extends LocalizedException
{
    public function __construct()
    {
        parent::__construct(__('This social account is already linked to another customer.'));
    }
}
