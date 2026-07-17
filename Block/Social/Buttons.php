<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Block\Social;

use Magento\Framework\View\Element\Template;
use Yu\SocialLogin\Model\Config;

class Buttons extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Child Button blocks rendered in Sort Order from the admin config;
     * children of other classes render after them, in layout order.
     *
     * @return string
     */
    public function getSortedButtonsHtml(): string
    {
        $buttons = [];
        $others = [];
        foreach ($this->getLayout()->getChildBlocks($this->getNameInLayout()) as $child) {
            if ($child instanceof Button) {
                $buttons[] = $child;
            } else {
                $others[] = $child;
            }
        }
        // usort is stable since PHP 8.0: equal sort_order keeps layout order.
        usort(
            $buttons,
            fn (Button $a, Button $b) =>
                $this->config->getProviderSortOrder($a->getProviderCode())
                <=> $this->config->getProviderSortOrder($b->getProviderCode())
        );
        $html = '';
        foreach ([...$buttons, ...$others] as $child) {
            $html .= $child->toHtml();
        }

        return $html;
    }
}
