<?php
/**
 * Jscriptz SmartShipping - Create Label Admin Controller
 *
 * Handles the "Create Label" button action from the order view page.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Controller\Adminhtml\Shipment;

use Jscriptz\SmartShipping\Api\ShipmentServiceInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Psr\Log\LoggerInterface;

class CreateLabel extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Sales::ship';

    public function __construct(
        Context $context,
        private readonly ShipmentServiceInterface $shipmentService,
        private readonly JsonFactory $jsonFactory,
        private readonly BackendUrlInterface $backendUrl,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Execute create label action
     */
    public function execute()
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        $notify = (bool) $this->getRequest()->getParam('notify', true);
        $isAjax = $this->getRequest()->isAjax();

        if (!$orderId) {
            if ($isAjax) {
                $result = $this->jsonFactory->create();
                return $result->setData([
                    'success' => false,
                    'message' => __('Order ID is required.'),
                ]);
            }

            $this->messageManager->addErrorMessage(__('Order ID is required.'));
            return $this->resultRedirectFactory->create()->setPath('sales/order/index');
        }

        try {
            // Check if order can be shipped
            if (!$this->shipmentService->canShip($orderId)) {
                throw new \Exception(__('Order cannot be shipped.')->render());
            }

            // Check if label is available
            if (!$this->shipmentService->isLabelAvailable($orderId)) {
                throw new \Exception(__('Label generation is not available for this order\'s shipping carrier.')->render());
            }

            // Get package data from request (optional)
            $packageData = [];
            if ($weight = $this->getRequest()->getParam('weight')) {
                $packageData['weight'] = (float) $weight;
            }
            if ($length = $this->getRequest()->getParam('length')) {
                $packageData['length'] = (float) $length;
            }
            if ($width = $this->getRequest()->getParam('width')) {
                $packageData['width'] = (float) $width;
            }
            if ($height = $this->getRequest()->getParam('height')) {
                $packageData['height'] = (float) $height;
            }

            // Create shipment with label
            $result = $this->shipmentService->createWithLabel($orderId, $packageData, $notify);

            if (!$result->isSuccess()) {
                throw new \Exception($result->getErrorMessage() ?? __('Failed to create label.')->render());
            }

            $successMessage = __(
                'Shipment created successfully. Tracking number: %1',
                $result->getTrackingNumber()
            );

            // Generate label URLs
            $shipmentId = $result->getShipmentId();
            $viewLabelUrl = $this->backendUrl->getUrl('smartshipping/shipment/getLabel', [
                'shipment_id' => $shipmentId,
                'mode' => 'view',
            ]);
            $downloadLabelUrl = $this->backendUrl->getUrl('smartshipping/shipment/getLabel', [
                'shipment_id' => $shipmentId,
                'mode' => 'download',
            ]);

            if ($isAjax) {
                $jsonResult = $this->jsonFactory->create();
                return $jsonResult->setData([
                    'success' => true,
                    'message' => $successMessage->render(),
                    'shipment_id' => $shipmentId,
                    'tracking_number' => $result->getTrackingNumber(),
                    'label_view_url' => $viewLabelUrl,
                    'label_download_url' => $downloadLabelUrl,
                ]);
            }

            $this->messageManager->addSuccessMessage($successMessage);
            return $this->resultRedirectFactory->create()->setPath(
                'sales/shipment/view',
                ['shipment_id' => $shipmentId]
            );

        } catch (\Exception $e) {
            $this->logger->error('[SmartShipping] Create label failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
            ]);

            if ($isAjax) {
                $jsonResult = $this->jsonFactory->create();
                return $jsonResult->setData([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setPath(
                'sales/order/view',
                ['order_id' => $orderId]
            );
        }
    }
}
