<?php
/**
 * Jscriptz SmartShipping - Callback URL Frontend Model
 *
 * Displays the webhook callback URL in admin config (read-only).
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Block\Adminhtml\System\Config;

use Jscriptz\SmartShipping\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class CallbackUrl extends Field
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render the callback URL as read-only text
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $callbackUrl = $this->config->getGenericWebhookCallbackUrl();

        $element->setValue($callbackUrl);
        $element->setReadonly(true);
        $element->addClass('admin__control-text');

        // Add copy button and carrier info
        $infoHtml = $this->_getInfoHtml();
        $copyButton = $this->_getCopyButton($callbackUrl);

        return $element->getElementHtml() . $copyButton . $infoHtml;
    }

    /**
     * Get the copy-to-clipboard button HTML
     */
    private function _getCopyButton(string $url): string
    {
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES);

        return <<<HTML
<button type="button" class="action-default scalable"
        onclick="navigator.clipboard.writeText('{$escapedUrl}').then(() => alert('Copied to clipboard!'))"
        style="margin-left: 10px; padding: 5px 10px;">
    <span>Copy URL</span>
</button>
HTML;
    }

    /**
     * Get information about carrier-specific URLs
     */
    private function _getInfoHtml(): string
    {
        $fedexUrl = $this->config->getWebhookCallbackUrl('fedexv3');
        $upsUrl = $this->config->getWebhookCallbackUrl('upsv3');
        $uspsUrl = $this->config->getWebhookCallbackUrl('uspsv3');

        return <<<HTML
<div style="margin-top: 10px; font-size: 12px; color: #666;">
    <strong>Carrier-specific URLs:</strong>
    <ul style="margin: 5px 0 0 20px; padding: 0;">
        <li>FedEx: <code style="background: #f5f5f5; padding: 2px 5px;">{$fedexUrl}</code></li>
        <li>UPS: <code style="background: #f5f5f5; padding: 2px 5px;">{$upsUrl}</code></li>
        <li>USPS: <code style="background: #f5f5f5; padding: 2px 5px;">{$uspsUrl}</code></li>
    </ul>
</div>
HTML;
    }
}
