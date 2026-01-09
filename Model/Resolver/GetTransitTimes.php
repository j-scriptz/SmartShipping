<?php
/**
 * Jscriptz SmartShipping - GetTransitTimes GraphQL Resolver
 *
 * Returns transit time data for shipping methods from all carriers.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Resolver;

use Jscriptz\SmartShipping\Api\TransitTimeRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class GetTransitTimes implements ResolverInterface
{
    public function __construct(
        private readonly TransitTimeRepositoryInterface $transitTimeRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $transitTimes = $this->transitTimeRepository->getAll();

        if (empty($transitTimes)) {
            return [];
        }

        $result = [];
        foreach ($transitTimes as $transitTime) {
            $result[] = [
                'carrier_code' => $transitTime->getCarrierCode(),
                'method_code' => $transitTime->getMethodCode(),
                'min_days' => $transitTime->getMinDays(),
                'max_days' => $transitTime->getMaxDays(),
                'delivery_date' => $transitTime->getDeliveryDate(),
                'delivery_day' => $transitTime->getDeliveryDay(),
                'delivery_time' => $transitTime->getDeliveryTime(),
                'guaranteed' => $transitTime->isGuaranteed(),
                'cutoff_hour' => $transitTime->getCutoffHour(),
            ];
        }

        return $result;
    }
}
