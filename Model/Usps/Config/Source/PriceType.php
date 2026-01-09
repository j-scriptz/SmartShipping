<?php
/**
 * Jscriptz SmartShipping - USPS Price Type Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PriceType implements OptionSourceInterface
{
    public const RETAIL = 'RETAIL';
    public const COMMERCIAL = 'COMMERCIAL';
    public const COMMERCIAL_PLUS = 'COMMERCIAL_PLUS';
    public const CONTRACT = 'CONTRACT';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::RETAIL, 'label' => __('Retail (Standard pricing)')],
            ['value' => self::COMMERCIAL, 'label' => __('Commercial (Discounted business rates)')],
            ['value' => self::COMMERCIAL_PLUS, 'label' => __('Commercial Plus (High-volume discounts)')],
            ['value' => self::CONTRACT, 'label' => __('Contract (Custom contract pricing)')],
        ];
    }

    public function toArray(): array
    {
        return [
            self::RETAIL => __('Retail'),
            self::COMMERCIAL => __('Commercial'),
            self::COMMERCIAL_PLUS => __('Commercial Plus'),
            self::CONTRACT => __('Contract'),
        ];
    }
}
