<?php
/**
 * Jscriptz SmartShipping - FedEx Label Format Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LabelFormat implements OptionSourceInterface
{
    public const PDF = 'PDF';
    public const PNG = 'PNG';
    public const ZPLII = 'ZPLII';
    public const EPL2 = 'EPL2';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::PDF, 'label' => __('PDF')],
            ['value' => self::PNG, 'label' => __('PNG Image')],
            ['value' => self::ZPLII, 'label' => __('ZPL II (Thermal)')],
            ['value' => self::EPL2, 'label' => __('EPL2 (Thermal)')],
        ];
    }
}
