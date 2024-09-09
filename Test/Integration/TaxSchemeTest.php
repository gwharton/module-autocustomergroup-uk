<?php

namespace Gw\AutoCustomerGroupUk\Test\Integration;

use Gw\AutoCustomerGroupUk\Model\TaxScheme;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @magentoAppArea frontend
 */
class TaxSchemeTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var TaxScheme
     */
    private $taxScheme;

    /**
     * @var ReinitableConfigInterface
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var GuestCartManagementInterface
     */
    private $guestCartManagement;

    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->taxScheme = $this->objectManager->get(TaxScheme::class);
        $this->config = $this->objectManager->get(ReinitableConfigInterface::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productFactory = $this->objectManager->get(ProductFactory::class);
        $this->guestCartManagement = $this->objectManager->get(GuestCartManagementInterface::class);
        $this->guestCartRepository = $this->objectManager->get(GuestCartRepositoryInterface::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * @magentoAdminConfigFixture currency/options/default GBP
     * @magentoAdminConfigFixture currency/options/base GBP
     * @magentoConfigFixture current_store autocustomergroup/ukvat/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/ukvat/exchangerate 1
     * @dataProvider getOrderValueDataProvider
     */
    public function testGetOrderValue(
        $qty1,
        $price1,
        $qty2,
        $price2,
        $expectedValue
    ): void {
        $product1 = $this->productFactory->create();
        $product1->setTypeId('simple')
            ->setId(1)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product 1')
            ->setSku('simple1')
            ->setPrice($price1)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0]);
        $this->productRepository->save($product1);
        $product2 = $this->productFactory->create();
        $product2->setTypeId('simple')
            ->setId(2)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product 2')
            ->setSku('simple2')
            ->setPrice($price2)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0]);
        $this->productRepository->save($product2);
        $maskedCartId = $this->guestCartManagement->createEmptyCart();
        $quote = $this->guestCartRepository->get($maskedCartId);
        $quote->addProduct($product1, $qty1);
        $quote->addProduct($product2, $qty2);
        $this->quoteRepository->save($quote);
        $result = $this->taxScheme->getOrderValue(
            $quote
        );
        $this->assertEqualsWithDelta($expectedValue, $result, 0.009);
    }
    /**
     * Remember for UK, it is the sum of the item prices that counts,
     * i.e total order value including discounts without shipping
     *
     * @return array
     */
    public function getOrderValueDataProvider(): array
    {
        // Quantity 1
        // Base Price 1
        // Quantity 2
        // Base Price 2
        // Expected Order Value Scheme Currency
        return [
            [1, 99.99, 2, 1.50, 102.99],   // 102.99GBP in GBP
            [1, 100.00, 3, 0.99, 102.97],  // 102.97GBP in GBP
            [2, 100.00, 1, 100, 300.00],   // 300.00GBP in GBP
            [7, 25.50, 3, 10.00, 208.50]   // 208.50GBP in GBP
        ];
    }

    /**
     * @magentoConfigFixture current_store autocustomergroup/ukvat/domestic 1
     * @magentoConfigFixture current_store autocustomergroup/ukvat/intraeub2b 2
     * @magentoConfigFixture current_store autocustomergroup/ukvat/intraeub2c 3
     * @magentoConfigFixture current_store autocustomergroup/ukvat/importb2b 4
     * @magentoConfigFixture current_store autocustomergroup/ukvat/importtaxed 5
     * @magentoConfigFixture current_store autocustomergroup/ukvat/importuntaxed 6
     * @magentoConfigFixture current_store autocustomergroup/ukvat/importthreshold 135
     * @dataProvider getCustomerGroupDataProvider
     */
    public function testGetCustomerGroup(
        $merchantCountryCode,
        $merchantPostCode,
        $customerCountryCode,
        $customerPostCode,
        $taxIdValidated,
        $orderValue,
        $expectedGroup
    ): void {
        $storeId = $this->storeManager->getStore()->getId();
        $this->config->setValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            $merchantCountryCode,
            ScopeInterface::SCOPE_STORE
        );
        $this->config->setValue(
            StoreInformation::XML_PATH_STORE_INFO_POSTCODE,
            $merchantPostCode,
            ScopeInterface::SCOPE_STORE
        );
        $result = $this->taxScheme->getCustomerGroup(
            $customerCountryCode,
            $customerPostCode,
            $taxIdValidated,
            $orderValue,
            $storeId
        );
        $this->assertEquals($expectedGroup, $result);
    }

    /**
     * @return array
     */
    public function getCustomerGroupDataProvider(): array
    {
        //Merchant Country Code
        //Merchant Post Code
        //Customer Country Code
        //Customer Post Code
        //taxIdValidated
        //OrderValue
        //Expected Group
        return [
            // UK/IM to UK/IM, value doesn't matter, VAT number status doesn't matter - Domestic
            ['GB', null, 'GB', null, false, 134, 1],
            ['GB', null, 'GB', null, false, 136, 1],
            ['GB', null, 'GB', 'BT1 1AA', false, 134, 1],
            ['GB', 'BT1 1AA', 'GB', null, false, 134, 1],
            ['GB', 'BT1 1AA', 'GB', null, true, 134, 1],
            ['IM', null, 'IM', null, false, 134, 1],
            ['GB', null, 'GB', null, false, 134, 1],
            // EU into NI, value doesn't matter, valid VAT - Intra-EU B2B
            ['FR', null, 'GB', 'BT1 1AA', true, 134, 2],
            ['FR', null, 'GB', 'BT1 1AA', true, 136, 2],
            // EU into NI, value doesn't matter, invalid VAT - Intra-EU B2C
            ['FR', null, 'GB', 'BT1 1AA', false, 134, 3],
            ['FR', null, 'GB', 'BT1 1AA', false, 136, 3],
            // Import into GB (Not NI), value doesn't matter, valid VAT - Import B2B
            ['FR', null, 'GB', null, true, 134, 4],
            ['FR', null, 'GB', null, true, 136, 4],
            // Import into GB (Not NI), order value below or equal to threshold, Only B2C left at this point - Import Taxed
            ['FR', null, 'GB', null, false, 134, 5],
            ['FR', null, 'GB', null, false, 134, 5],
            // Import into GB (Not NI), order value above threshold, Only B2C left at this point - Import Untaxed
            ['FR', null, 'GB', null, false, 136, 6],
            ['FR', null, 'GB', null, false, 136, 6],
            ['US', null, 'GB', null, false, 136, 6],
            ['US', null, 'GB', null, false, 136, 6],
        ];
    }

    /**
     * @magentoConfigFixture current_store autocustomergroup/ukvat/registrationnumber GB553557881
     * @magentoConfigFixture current_store autocustomergroup/ukvat/environment sandbox
     * @magentoConfigFixture current_store autocustomergroup/ukvat/validate_online 1
     * @dataProvider checkTaxIdDataProviderOnline
     */
    public function testCheckTaxIdOnline(
        $countryCode,
        $taxId,
        $isValid
    ): void {
        $result = $this->taxScheme->checkTaxId(
            $countryCode,
            $taxId
        );
        $this->assertEquals($isValid, $result->getIsValid());
    }

    /**
     * @return array
     * https://github.com/hmrc/vat-registered-companies-api/blob/main/public/api/conf/1.0/test-data/vrn.csv
     */
    public function checkTaxIdDataProviderOnline(): array
    {
        //Country code
        //Tax Id
        //IsValid
        return [
            ['GB', '',                  false],
            ['GB', null,                false],
            ['GB', 'GB553557881',       true], // valid VAT number
            ['GB', 'GB146295999727',    true], // valid VAT number
            ['GB', 'GB948561936944',    true], // valid VAT number
            ['GB', 'GB166804280212',    true], // valid VAT number
            ['GB', 'GB726129090',       true], // valid VAT number
            ['GB', 'GB576042213',       true], // valid VAT number
            ['GB', 'GB786176152',       true], // valid VAT number
            ['GB', '726129090',         true], // valid VAT number
            ['GB', '576042213',         true], // valid VAT number
            ['GB', '786176152',         true], // valid VAT number
            ['GB', '34634643634',       false],
            ['GB', 'rheahehetr',        false],
            ['GB', 'arhehreareaf',      false],
            ['GB', '123456789',         false],
            ['GB', '123456789012',      false],
            ['US', 'GB553557881',       false] // Unsupported country, despite valid VAT number
        ];
    }

    /**
     * @magentoConfigFixture current_store autocustomergroup/ukvat/registrationnumber GB553557881
     * @magentoConfigFixture current_store autocustomergroup/ukvat/environment sandbox
     * @dataProvider checkTaxIdDataProviderOffline
     */
    public function testCheckTaxIdOffline(
        $countryCode,
        $taxId,
        $isValid
    ): void {
        $result = $this->taxScheme->checkTaxId(
            $countryCode,
            $taxId
        );
        $this->assertEquals($isValid, $result->getIsValid());
    }

    /**
     * @return array
     */
    public function checkTaxIdDataProviderOffline(): array
    {
        //Country code
        //Tax Id
        //IsValid
        return [
            ['GB', '',                  false],
            ['GB', null,                false],
            ['GB', 'GB573733578',       true], // valid format
            ['GB', 'GB535634643466',    true], // valid format
            ['GB', 'GB546365654577',    true], // valid format
            ['GB', 'GB435663735666',    true], // valid format
            ['GB', 'GBHA645',           true], // valid format
            ['GB', 'GBGD543',           true], // valid format
            ['GB', 'GB786176152',       true], // valid format
            ['IM', '726129090',         true], // valid format
            ['GB', '576042213',         true], // valid format
            ['GB', '786176152',         true], // valid format
            ['GB', 'HA645',             true], // valid format
            ['GB', 'GD543',             true], // valid format
            ['GB', 'GB786176152',       true], // valid format
            ['GB', 'AB432',             false],
            ['GB', 'GBRT456',           false],
            ['GB', 'arhehreareaf',      false],
        ];
    }
}
