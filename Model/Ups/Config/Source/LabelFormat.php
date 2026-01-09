<?php
/**
 * Jscriptz SmartShipping - UPS Label Format Source
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LabelFormat implements OptionSourceInterface
{
    /**
     * UPS supported label formats
     */
    public const GIF = 'GIF';
    public const PNG = 'PNG';
    public const PDF = 'PDF';
    public const ZPL = 'ZPL';
    public const EPL = 'EPL';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::GIF, 'label' => __('GIF (Standard thermal/inkjet)')],
            ['value' => self::PNG, 'label' => __('PNG (High resolution)')],
            ['value' => self::PDF, 'label' => __('PDF (For office printers)')],
            ['value' => self::ZPL, 'label' => __('ZPL (Zebra thermal printers)')],
            ['value' => self::EPL, 'label' => __('EPL (Eltron thermal printers)')],
        ];
    }
}
