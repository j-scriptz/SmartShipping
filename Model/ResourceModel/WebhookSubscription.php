<?php
/**
 * Jscriptz SmartShipping - Webhook Subscription Resource Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\ResourceModel;

use Jscriptz\SmartShipping\Api\Data\WebhookSubscriptionInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class WebhookSubscription extends AbstractDb
{
    public const TABLE_NAME = 'jscriptz_shipping_webhook_subscription';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, WebhookSubscriptionInterface::ENTITY_ID);
    }
}
