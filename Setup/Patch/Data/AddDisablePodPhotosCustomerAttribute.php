<?php
/**
 * Jscriptz SmartShipping - Customer Attribute Setup
 *
 * Adds the 'disable_pod_photos' customer attribute for POD photo preferences.
 * This attribute may also be created by UPSv3, FEDEXv3, or ShippingCore - the patch checks for existence first.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddDisablePodPhotosCustomerAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeSetFactory $attributeSetFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Check if attribute already exists using direct DB query to avoid EAV cache issues
        $entityTypeId = $customerSetup->getEntityTypeId(Customer::ENTITY);
        if ($this->attributeExistsInDb('disable_pod_photos', (int) $entityTypeId)) {
            // Attribute already exists (created by UPSv3, FEDEXv3, or ShippingCore), skip creation
            $this->moduleDataSetup->getConnection()->endSetup();
            return $this;
        }

        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = (int) $customerEntity->getDefaultAttributeSetId();

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = (int) $attributeSet->getDefaultGroupId($attributeSetId);

        $customerSetup->addAttribute(
            Customer::ENTITY,
            'disable_pod_photos',
            [
                'type' => 'int',
                'label' => 'Disable Delivery Photos in Emails',
                'input' => 'boolean',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'required' => false,
                'default' => '0',
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 1000,
                'position' => 1000,
                'system' => false,
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => false,
            ]
        );

        $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'disable_pod_photos');
        $attribute->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => [
                'adminhtml_customer',
                'customer_account_edit',
            ],
        ]);
        $attribute->save();

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Check if attribute exists in database (bypasses EAV cache)
     */
    private function attributeExistsInDb(string $attributeCode, int $entityTypeId): bool
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('eav_attribute');

        $select = $connection->select()
            ->from($table, ['attribute_id'])
            ->where('attribute_code = ?', $attributeCode)
            ->where('entity_type_id = ?', $entityTypeId);

        return (bool) $connection->fetchOne($select);
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
