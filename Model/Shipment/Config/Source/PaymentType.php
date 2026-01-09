<?php
/**
 * Jscriptz SmartShipping - Payment Account Type Source
 *
 * USPS payment account types for label generation.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Shipment\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PaymentType implements OptionSourceInterface
{
    public const EPS = 'EPS';
    public const PERMIT = 'PERMIT';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::EPS,
                'label' => __('EPS (Enterprise Payment System)'),
            ],
            [
                'value' => self::PERMIT,
                'label' => __('Permit'),
            ],
        ];
    }
}
