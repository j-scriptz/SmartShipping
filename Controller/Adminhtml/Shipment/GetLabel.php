<?php
/**
 * Jscriptz SmartShipping - Get Label Controller
 *
 * Serves shipping label for download or viewing in browser.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Psr\Log\LoggerInterface;

class GetLabel extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Sales::shipment';

    public function __construct(
        Context $context,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly FileFactory $fileFactory,
        private readonly RawFactory $rawFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Get label action
     *
     * Modes:
     * - download: Forces file download
     * - view: Opens in browser (inline)
     */
    public function execute()
    {
        $shipmentId = (int) $this->getRequest()->getParam('shipment_id');
        $mode = $this->getRequest()->getParam('mode', 'view'); // 'download' or 'view'

        if (!$shipmentId) {
            $this->messageManager->addErrorMessage(__('Shipment ID is required.'));
            return $this->resultRedirectFactory->create()->setPath('sales/shipment/index');
        }

        try {
            $shipment = $this->shipmentRepository->get($shipmentId);
            $labelContent = $shipment->getShippingLabel();

            if (empty($labelContent)) {
                $this->messageManager->addErrorMessage(__('No shipping label found for this shipment.'));
                return $this->resultRedirectFactory->create()->setPath(
                    'sales/shipment/view',
                    ['shipment_id' => $shipmentId]
                );
            }

            // Detect format from content (PDF starts with %PDF)
            $format = $this->detectFormat($labelContent);
            $contentType = $this->getContentType($format);
            $extension = strtolower($format);

            // Get carrier name and order number for filename
            $order = $shipment->getOrder();
            $carrierCode = $this->getCarrierName($order->getShippingMethod());
            $orderNumber = $order->getIncrementId();

            $filename = sprintf(
                '%s_SHIPPING_LABEL_%s.%s',
                strtoupper($carrierCode),
                $orderNumber,
                $extension
            );

            if ($mode === 'download') {
                // Force download
                return $this->fileFactory->create(
                    $filename,
                    $labelContent,
                    \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
                    $contentType
                );
            }

            // View in browser (inline)
            $result = $this->rawFactory->create();
            $result->setHeader('Content-Type', $contentType);
            $result->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"');
            $result->setContents($labelContent);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('[SmartShipping] Failed to get label: ' . $e->getMessage(), [
                'shipment_id' => $shipmentId,
            ]);
            $this->messageManager->addErrorMessage(__('Failed to retrieve shipping label.'));
            return $this->resultRedirectFactory->create()->setPath('sales/shipment/index');
        }
    }

    /**
     * Detect label format from content
     */
    private function detectFormat(string $content): string
    {
        // Check for PDF magic bytes
        if (str_starts_with($content, '%PDF')) {
            return 'PDF';
        }

        // Check for PNG magic bytes
        if (str_starts_with($content, "\x89PNG")) {
            return 'PNG';
        }

        // Check for ZPL (starts with ^XA typically)
        if (str_starts_with($content, '^XA')) {
            return 'ZPL';
        }

        // Default to PDF
        return 'PDF';
    }

    /**
     * Get content type for format
     */
    private function getContentType(string $format): string
    {
        return match (strtoupper($format)) {
            'PDF' => 'application/pdf',
            'PNG' => 'image/png',
            'ZPL' => 'application/octet-stream',
            default => 'application/pdf',
        };
    }

    /**
     * Get carrier name from shipping method
     */
    private function getCarrierName(string $shippingMethod): string
    {
        // Extract carrier code from shipping method (e.g., "fedexv3_GROUND" -> "fedexv3")
        $carrierCode = explode('_', $shippingMethod)[0] ?? '';

        // Map carrier codes to display names
        return match (strtolower($carrierCode)) {
            'fedexv3', 'fedex' => 'FEDEX',
            'upsv3', 'ups' => 'UPS',
            'uspsv3', 'usps' => 'USPS',
            'dhl' => 'DHL',
            default => strtoupper($carrierCode) ?: 'SHIPPING',
        };
    }
}
