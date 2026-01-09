<?php
/**
 * Jscriptz SmartShipping - Add Label Button Plugin
 *
 * Adds a "Create Shipping Label" button to the order view page
 * for orders that can be shipped and have label generation available.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Plugin\Adminhtml;

use Jscriptz\SmartShipping\Api\ShipmentServiceInterface;
use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Toolbar\Interceptor as ToolbarInterceptor;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Sales\Block\Adminhtml\Order\View;

class AddLabelButtonToOrderView
{
    public function __construct(
        private readonly ShipmentServiceInterface $shipmentService
    ) {
    }

    /**
     * Add "Create Shipping Label" button before toolbar
     */
    public function beforePushButtons(
        ToolbarInterceptor $toolbar,
        AbstractBlock $context,
        ButtonList $buttonList
    ): array {
        if (!$context instanceof View) {
            return [$context, $buttonList];
        }

        $order = $context->getOrder();
        if (!$order || !$order->getId()) {
            return [$context, $buttonList];
        }

        $orderId = (int) $order->getId();

        // Only show button if order can be shipped
        if (!$this->shipmentService->canShip($orderId)) {
            return [$context, $buttonList];
        }

        // Only show button if label generation is available for this carrier
        if (!$this->shipmentService->isLabelAvailable($orderId)) {
            return [$context, $buttonList];
        }

        $url = $context->getUrl(
            'smartshipping/shipment/createLabel',
            ['order_id' => $orderId]
        );

        $buttonList->add(
            'smartshipping_create_label',
            [
                'label' => __('Create Shipping Label'),
                'onclick' => sprintf(
                    "setLocation('%s')",
                    $url
                ),
                'class' => 'ship primary',
                'sort_order' => 15, // After "Ship" button
            ],
            -1,
            'header'
        );

        return [$context, $buttonList];
    }
}
