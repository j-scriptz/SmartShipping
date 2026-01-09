<?php
/**
 * Jscriptz SmartShipping - USPS Environment Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    public const SANDBOX = 'sandbox';
    public const PRODUCTION = 'production';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::SANDBOX, 'label' => __('Sandbox (TEM - Testing Environment)')],
            ['value' => self::PRODUCTION, 'label' => __('Production')],
        ];
    }

    public function toArray(): array
    {
        return [
            self::SANDBOX => __('Sandbox'),
            self::PRODUCTION => __('Production'),
        ];
    }
}
