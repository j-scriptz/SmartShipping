<?php
/**
 * Jscriptz SmartShipping - Grace Period Unit Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class GracePeriodUnit implements OptionSourceInterface
{
    public const DAYS = 'days';
    public const HOURS = 'hours';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::DAYS, 'label' => __('Days')],
            ['value' => self::HOURS, 'label' => __('Hours')],
        ];
    }
}
