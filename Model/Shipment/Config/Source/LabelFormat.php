<?php
/**
 * Jscriptz SmartShipping - Label Format Source
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Shipment\Config\Source;

use Jscriptz\SmartShipping\Model\Shipment\Config;
use Magento\Framework\Data\OptionSourceInterface;

class LabelFormat implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::FORMAT_PDF,
                'label' => __('PDF'),
            ],
            [
                'value' => Config::FORMAT_PNG,
                'label' => __('PNG'),
            ],
            [
                'value' => Config::FORMAT_ZPL,
                'label' => __('ZPL (Thermal Printer)'),
            ],
        ];
    }
}
