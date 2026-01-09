<?php
/**
 * Jscriptz SmartShipping - Weekdays Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Weekdays implements OptionSourceInterface
{
    public const SUNDAY = 0;
    public const MONDAY = 1;
    public const TUESDAY = 2;
    public const WEDNESDAY = 3;
    public const THURSDAY = 4;
    public const FRIDAY = 5;
    public const SATURDAY = 6;

    public function toOptionArray(): array
    {
        return [
            ['value' => self::SUNDAY, 'label' => __('Sunday')],
            ['value' => self::MONDAY, 'label' => __('Monday')],
            ['value' => self::TUESDAY, 'label' => __('Tuesday')],
            ['value' => self::WEDNESDAY, 'label' => __('Wednesday')],
            ['value' => self::THURSDAY, 'label' => __('Thursday')],
            ['value' => self::FRIDAY, 'label' => __('Friday')],
            ['value' => self::SATURDAY, 'label' => __('Saturday')],
        ];
    }

    /**
     * Get day name by number
     */
    public function getDayName(int $dayNumber): string
    {
        $days = [
            self::SUNDAY => 'Sunday',
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
        ];

        return $days[$dayNumber] ?? '';
    }
}
