<?php
/**
 * Jscriptz SmartShipping - Customer Shipping Preferences Page Controller
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Controller\Customer;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class ShippingPreferences extends AbstractAccount implements HttpGetActionInterface
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Shipping Notification Preferences'));

        return $resultPage;
    }
}
