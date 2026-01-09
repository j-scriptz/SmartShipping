<?php
/**
 * Jscriptz SmartShipping - FedEx Environment Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    public const SANDBOX = 'sandbox';
    public const PRODUCTION = 'production';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::SANDBOX, 'label' => __('Sandbox (Testing)')],
            ['value' => self::PRODUCTION, 'label' => __('Production (Live)')],
        ];
    }
}
