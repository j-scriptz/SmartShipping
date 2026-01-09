<?php
/**
 * Jscriptz SmartShipping - Customer Shipping Preferences Block
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Block\Customer\Account;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class ShippingPreferences extends Template
{
    protected $_template = 'Jscriptz_SmartShipping::customer/account/shipping_preferences.phtml';

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get save URL for the form
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl('smartshipping/customer/savePreferences');
    }

    /**
     * Check if POD photos are disabled for the current customer
     */
    public function isPodPhotosDisabled(): bool
    {
        $customerId = $this->customerSession->getCustomerId();
        if (!$customerId) {
            return false;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $attribute = $customer->getCustomAttribute('disable_pod_photos');
            return $attribute && (bool) $attribute->getValue();
        } catch (\Exception $e) {
            return false;
        }
    }
}
