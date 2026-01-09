<?php
/**
 * Jscriptz SmartShipping - Dimension Unit Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DimensionUnit implements OptionSourceInterface
{
    public const IN = 'IN';
    public const CM = 'CM';
    public const FT = 'FT';
    public const M = 'M';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::IN, 'label' => __('Inches (IN)')],
            ['value' => self::CM, 'label' => __('Centimeters (CM)')],
            ['value' => self::FT, 'label' => __('Feet (FT)')],
            ['value' => self::M, 'label' => __('Meters (M)')],
        ];
    }

    /**
     * Convert dimension to inches
     */
    public static function toInches(float $dimension, string $fromUnit): float
    {
        return match ($fromUnit) {
            self::CM => $dimension / 2.54,
            self::FT => $dimension * 12,
            self::M => $dimension * 39.3701,
            default => $dimension,
        };
    }

    /**
     * Convert dimension from inches
     */
    public static function fromInches(float $dimension, string $toUnit): float
    {
        return match ($toUnit) {
            self::CM => $dimension * 2.54,
            self::FT => $dimension / 12,
            self::M => $dimension / 39.3701,
            default => $dimension,
        };
    }
}
