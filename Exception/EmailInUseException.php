<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Exception;

use Magento\Framework\Exception\LocalizedException;

class EmailInUseException extends LocalizedException
{
    public function __construct()
    {
        parent::__construct(
            __('This email is already registered. Please sign in with your password and link the social account in your account settings.')
        );
    }
}
