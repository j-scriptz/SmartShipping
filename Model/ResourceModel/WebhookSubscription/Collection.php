<?php
/**
 * Jscriptz SmartShipping - Webhook Subscription Collection
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\ResourceModel\WebhookSubscription;

use Jscriptz\SmartShipping\Api\Data\WebhookSubscriptionInterface;
use Jscriptz\SmartShipping\Model\WebhookSubscription as Model;
use Jscriptz\SmartShipping\Model\ResourceModel\WebhookSubscription as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = WebhookSubscriptionInterface::ENTITY_ID;

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * Filter by carrier code
     */
    public function addCarrierFilter(string $carrierCode): self
    {
        return $this->addFieldToFilter(WebhookSubscriptionInterface::CARRIER_CODE, $carrierCode);
    }

    /**
     * Filter by subscription type
     */
    public function addTypeFilter(string $type): self
    {
        return $this->addFieldToFilter(WebhookSubscriptionInterface::SUBSCRIPTION_TYPE, $type);
    }

    /**
     * Filter by status
     */
    public function addStatusFilter(string $status): self
    {
        return $this->addFieldToFilter(WebhookSubscriptionInterface::STATUS, $status);
    }

    /**
     * Filter active subscriptions only
     */
    public function addActiveFilter(): self
    {
        $this->addStatusFilter(WebhookSubscriptionInterface::STATUS_ACTIVE);

        // Exclude expired
        $this->getSelect()->where(
            WebhookSubscriptionInterface::EXPIRES_AT . ' IS NULL OR ' .
            WebhookSubscriptionInterface::EXPIRES_AT . ' > NOW()'
        );

        return $this;
    }

    /**
     * Filter by tracking number
     */
    public function addTrackingNumberFilter(string $trackingNumber): self
    {
        return $this->addFieldToFilter(WebhookSubscriptionInterface::TRACKING_NUMBER, $trackingNumber);
    }

    /**
     * Filter by account number
     */
    public function addAccountNumberFilter(string $accountNumber): self
    {
        return $this->addFieldToFilter(WebhookSubscriptionInterface::ACCOUNT_NUMBER, $accountNumber);
    }
}
