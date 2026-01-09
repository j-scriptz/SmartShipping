<?php
/**
 * Jscriptz SmartShipping - FedEx Label Stock Type Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LabelStockType implements OptionSourceInterface
{
    public const PAPER_4X6 = 'PAPER_4X6';
    public const PAPER_4X8 = 'PAPER_4X8';
    public const PAPER_4X9 = 'PAPER_4X9';
    public const PAPER_7X4 = 'PAPER_7X4.75';
    public const PAPER_8X11 = 'PAPER_8.5X11_BOTTOM_HALF_LABEL';
    public const PAPER_8X11_TOP = 'PAPER_8.5X11_TOP_HALF_LABEL';
    public const STOCK_4X6 = 'STOCK_4X6';
    public const STOCK_4X6_75 = 'STOCK_4X6.75_LEADING_DOC_TAB';
    public const STOCK_4X8 = 'STOCK_4X8';
    public const STOCK_4X9 = 'STOCK_4X9_LEADING_DOC_TAB';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::PAPER_4X6, 'label' => __('Paper 4x6')],
            ['value' => self::PAPER_4X8, 'label' => __('Paper 4x8')],
            ['value' => self::PAPER_4X9, 'label' => __('Paper 4x9')],
            ['value' => self::PAPER_7X4, 'label' => __('Paper 7x4.75')],
            ['value' => self::PAPER_8X11, 'label' => __('Paper 8.5x11 (Bottom Half)')],
            ['value' => self::PAPER_8X11_TOP, 'label' => __('Paper 8.5x11 (Top Half)')],
            ['value' => self::STOCK_4X6, 'label' => __('Stock 4x6 (Thermal)')],
            ['value' => self::STOCK_4X6_75, 'label' => __('Stock 4x6.75 with Doc Tab (Thermal)')],
            ['value' => self::STOCK_4X8, 'label' => __('Stock 4x8 (Thermal)')],
            ['value' => self::STOCK_4X9, 'label' => __('Stock 4x9 with Doc Tab (Thermal)')],
        ];
    }
}
