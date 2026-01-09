<?php
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders a read-only text field in system configuration
 */
class Disabled extends Field
{
    /**
     * Set the element to be disabled
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setDisabled('disabled');
        $element->setReadonly(true);
        $element->setData('style', 'background-color: #f0f0f0; cursor: not-allowed;');

        return parent::_getElementHtml($element);
    }
}
