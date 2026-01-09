<?php
/**
 * Jscriptz SmartShipping - Auto-Ship Trigger Source
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Shipment\Config\Source;

use Jscriptz\SmartShipping\Model\Shipment\Config;
use Magento\Framework\Data\OptionSourceInterface;

class Trigger implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::TRIGGER_MANUAL,
                'label' => __('Manual Only'),
            ],
            [
                'value' => Config::TRIGGER_ORDER_PLACE,
                'label' => __('When Order is Placed'),
            ],
            [
                'value' => Config::TRIGGER_INVOICE_CREATE,
                'label' => __('When Invoice is Created'),
            ],
        ];
    }
}
