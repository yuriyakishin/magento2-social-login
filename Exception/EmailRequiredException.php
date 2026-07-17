<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Exception;

use Magento\Framework\Exception\LocalizedException;

class EmailRequiredException extends LocalizedException
{
    public function __construct()
    {
        parent::__construct(__('The provider did not share your email address.'));
    }
}
