<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Model\HybridAuth\Provider;

use Hybridauth\Provider\Apple as BaseApple;

/**
 * Apple mandates the form_post response mode (a cross-site POST callback that
 * browsers strip the session cookie from) whenever the name/email scopes are
 * requested. With an empty scope Apple allows the regular GET (query) callback,
 * so the flow works like every other provider. When the id_token then carries
 * no email, the customer is routed to the standard "enter your email" form.
 */
class Apple extends BaseApple
{
    /**
     * {@inheritdoc}
     */
    protected $scope = '';

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        parent::initialize();
        $this->AuthorizeUrlParameters['response_mode'] = 'query';
    }
}
