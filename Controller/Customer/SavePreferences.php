<?php
/**
 * Jscriptz SmartShipping - Save Customer Shipping Preferences Controller
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Controller\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class SavePreferences extends AbstractAccount implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('smartshipping/customer/shippingPreferences');

        // Validate form key
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));
            return $resultRedirect;
        }

        $customerId = $this->customerSession->getCustomerId();
        if (!$customerId) {
            $this->messageManager->addErrorMessage(__('Please log in to save your preferences.'));
            $resultRedirect->setPath('customer/account/login');
            return $resultRedirect;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);

            // Get the checkbox value (1 if checked, null if not)
            $disablePodPhotos = $this->getRequest()->getParam('disable_pod_photos') ? '1' : '0';

            $customer->setCustomAttribute('disable_pod_photos', $disablePodPhotos);
            $this->customerRepository->save($customer);

            $this->messageManager->addSuccessMessage(__('Your shipping notification preferences have been saved.'));

        } catch (\Exception $e) {
            $this->logger->error('[SmartShipping] Failed to save customer preferences: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Unable to save your preferences. Please try again.'));
        }

        return $resultRedirect;
    }
}
