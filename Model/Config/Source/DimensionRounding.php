<?php
/**
 * Jscriptz SmartShipping - Dimension Rounding Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DimensionRounding implements OptionSourceInterface
{
    public const ROUND_UP = 'up';
    public const ROUND_DOWN = 'down';
    public const ROUND_NEAREST = 'nearest';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::ROUND_UP, 'label' => __('Round Up (Recommended)')],
            ['value' => self::ROUND_NEAREST, 'label' => __('Round to Nearest')],
            ['value' => self::ROUND_DOWN, 'label' => __('Round Down')],
        ];
    }

    /**
     * Apply rounding to a dimension value
     */
    public static function apply(float $value, string $method): int
    {
        return match ($method) {
            self::ROUND_UP => (int) ceil($value),
            self::ROUND_DOWN => (int) floor($value),
            default => (int) round($value),
        };
    }
}
