<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class RedirectUri extends Field
{
    public function __construct(
        Context $context,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render a readonly input with the provider's real redirect URI and a Copy button.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $url = $this->getRedirectUri($this->getProviderCode($element));
        $inputId = $element->getHtmlId() . '_value';

        return sprintf(
            '<input type="text" readonly class="admin__control-text" style="width:70%%" id="%1$s"'
            . ' value="%2$s" onclick="this.select()"/>'
            . ' <button type="button" class="action-default"'
            . ' onclick="var b=this;navigator.clipboard.writeText(document.getElementById(\'%1$s\').value)'
            . '.then(function(){b.innerText=\'%4$s\';setTimeout(function(){b.innerText=\'%3$s\';},2000);})">'
            . '%3$s</button>',
            $this->escapeHtmlAttr($inputId),
            $this->escapeHtmlAttr($url),
            $this->escapeHtml(__('Copy')),
            $this->escapeJs((string)__('Copied'))
        );
    }

    /**
     * Field html id is yu_social_login_<provider>_redirect_uri; provider codes
     * may contain underscores, so strip the fixed prefix/suffix instead of
     * splitting.
     *
     * @param AbstractElement $element
     * @return string
     */
    private function getProviderCode(AbstractElement $element): string
    {
        return (string)preg_replace('/^yu_social_login_(.+)_redirect_uri$/', '$1', $element->getHtmlId());
    }

    /**
     * Callback URL built from the base URL of the store in the current config scope.
     *
     * @param string $provider Provider code
     * @return string
     */
    private function getRedirectUri(string $provider): string
    {
        $storeCode = $this->getRequest()->getParam('store');
        if ($storeCode) {
            $store = $this->storeManager->getStore($storeCode);
        } else {
            $websiteCode = $this->getRequest()->getParam('website');
            $store = $websiteCode
                ? $this->storeManager->getWebsite($websiteCode)->getDefaultStore()
                : $this->storeManager->getDefaultStoreView();
        }

        return $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true)
            . 'social-login/social/callback/provider/' . $provider . '/';
    }
}
