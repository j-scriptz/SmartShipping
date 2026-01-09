<?php
/**
 * Jscriptz SmartShipping - Sort By Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SortBy implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'default', 'label' => __('Default (Carrier Sort Order)')],
            ['value' => 'price_asc', 'label' => __('Price: Low to High')],
            ['value' => 'price_desc', 'label' => __('Price: High to Low')],
            ['value' => 'time_asc', 'label' => __('Delivery Time: Fastest First')],
            ['value' => 'time_desc', 'label' => __('Delivery Time: Slowest First')],
        ];
    }
}
