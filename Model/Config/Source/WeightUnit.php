<?php
/**
 * Jscriptz SmartShipping - Weight Unit Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WeightUnit implements OptionSourceInterface
{
    public const LB = 'LB';
    public const KG = 'KG';
    public const OZ = 'OZ';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::LB, 'label' => __('Pounds (LB)')],
            ['value' => self::KG, 'label' => __('Kilograms (KG)')],
            ['value' => self::OZ, 'label' => __('Ounces (OZ)')],
        ];
    }

    /**
     * Convert weight to pounds
     */
    public static function toPounds(float $weight, string $fromUnit): float
    {
        return match ($fromUnit) {
            self::KG => $weight * 2.20462,
            self::OZ => $weight / 16,
            default => $weight,
        };
    }

    /**
     * Convert weight from pounds
     */
    public static function fromPounds(float $weight, string $toUnit): float
    {
        return match ($toUnit) {
            self::KG => $weight / 2.20462,
            self::OZ => $weight * 16,
            default => $weight,
        };
    }
}
