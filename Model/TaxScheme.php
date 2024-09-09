<?php

namespace Gw\AutoCustomerGroupUk\Model;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Gw\AutoCustomerGroup\Api\Data\TaxIdCheckResponseInterface;
use Gw\AutoCustomerGroup\Api\Data\TaxIdCheckResponseInterfaceFactory;
use Gw\AutoCustomerGroup\Api\Data\TaxSchemeInterface;
use Gw\AutoCustomerGroup\Model\Config\Source\Environment;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class TaxScheme implements TaxSchemeInterface
{
    const CODE = "ukvat";
    const SCHEME_CURRENCY = 'GBP';
    const array SCHEME_COUNTRIES = ['GB','IM'];
    const array EU_COUNTRIES = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
        'IT','LV','LT','LU','MT','MC','NL','PL','PT','RO','SK','SI','ES','SE'];

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var TaxIdCheckResponseInterfaceFactory
     */
    private $ticrFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CurrencyFactory
     */
    public $currencyFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param CurrencyFactory $currencyFactory
     * @param TaxIdCheckResponseInterfaceFactory $ticrFactory
     * @param ClientFactory $clientFactory
     * @param Json $serializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory,
        TaxIdCheckResponseInterfaceFactory $ticrFactory,
        ClientFactory $clientFactory,
        Json $serializer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        $this->ticrFactory = $ticrFactory;
        $this->clientFactory = $clientFactory;
        $this->serializer = $serializer;
    }

    /**
     * Get the order value, in scheme currency
     *
     * For the purposes of Scheme Threshold, the order value is defined as the total value of all products
     * on the order, less any discount, excluding any CIF costs.
     *
     * https://www.gov.uk/guidance/vat-and-overseas-goods-sold-directly-to-customers-in-the-uk
     *
     * @param Quote $quote
     * @return float
     */
    public function getOrderValue(Quote $quote): float
    {
        $orderValue = 0.0;
        foreach ($quote->getItemsCollection() as $item) {
            $orderValue += ($item->getBaseRowTotal() - $item->getBaseDiscountAmount());
        }
        return ($orderValue / $this->getSchemeExchangeRate($quote->getStoreId()));
    }

    /**
     * Get customer group based on Validation Result and Country of customer
     *
     * @param string $customerCountryCode
     * @param string|null $customerPostCode
     * @param bool $taxIdValidated
     * @param float $orderValue
     * @param int|null $storeId
     * @return int|null
     */
    public function getCustomerGroup(
        string $customerCountryCode,
        ?string $customerPostCode,
        bool $taxIdValidated,
        float $orderValue,
        ?int $storeId
    ): ?int {
        $merchantCountry = $this->scopeConfig->getValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (empty($merchantCountry)) {
            $this->logger->critical(
                "Gw/AutoCustomerGroupUk/Model/TaxScheme::getCustomerGroup() : " .
                "Merchant country not set."
            );
            return null;
        }

        if (empty($customerPostCode)) {
            // Make sure it's at least an empty string
            // We assume the customer is not in NI if they haven't set a postcode.
            $customerPostCode = "";
        }

        $importThreshold = $this->getThresholdInSchemeCurrency($storeId);

        // Merchant Country is in the UK/IM
        // Item shipped to the UK/IM
        // Therefore Domestic
        if (in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        // Merchant Country is in the EU
        // Item shipped to NI
        // VAT No is valid
        // Therefore Intra-EU B2B
        if (in_array($merchantCountry, self::EU_COUNTRIES) &&
            ($customerCountryCode == "GB" && preg_match("/^[Bb][Tt].*$/", $customerPostCode)) &&
            $taxIdValidated) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeub2b",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        // Merchant Country is in the EU
        // Item shipped to the NI
        // VAT No is not valid
        // Therefore Intra-EU B2C
        if (in_array($merchantCountry, self::EU_COUNTRIES) &&
            ($customerCountryCode == "GB" && preg_match("/^[Bb][Tt].*$/", $customerPostCode)) &&
            !$taxIdValidated) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeub2c",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        // Merchant Country is not in the UK/IM
        // Item shipped to the UK/IM
        // VAT No is valid
        // Therefore Import B2B
        if (!in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES) &&
            $taxIdValidated) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importb2b",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        // Merchant Country is not in the UK/IM
        // Item shipped to the UK/IM
        // Order value is equal or below threshold
        // Therefore Import Taxed
        if (!in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES) &&
            ($orderValue <= $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importtaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        // Merchant Country is not in the UK/IM
        // Item shipped to the UK/IM
        // Order value is above threshold
        // Therefore Import Untaxed
        if (!in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES) &&
            ($orderValue > $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importuntaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        return null;
    }

    /**
     * Peform validation of the VAT number, returning a gatewayResponse object
     *
     * @param string $countryCode
     * @param string|null $taxId
     * @return TaxIdCheckResponseInterface
     * @throws GuzzleException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): TaxIdCheckResponseInterface {
        $taxIdCheckResponse = $this->ticrFactory->create();

        if (!in_array($countryCode, self::SCHEME_COUNTRIES)) {
            $taxIdCheckResponse->setRequestMessage(__('Unsupported country.'));
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(false);
            return $taxIdCheckResponse;
        }

        $taxIdCheckResponse = $this->validateFormat($taxIdCheckResponse, $taxId);

        if ($taxIdCheckResponse->getIsValid() && $this->scopeConfig->isSetFlag(
                "autocustomergroup/" . self::CODE . "/validate_online",
                ScopeInterface::SCOPE_STORE
            )) {
            $taxIdCheckResponse = $this->validateOnline($taxIdCheckResponse, $taxId);
        }

        return $taxIdCheckResponse;
    }

    /**
     * Perform offline validation of the Tax Identifier
     *
     * @param $taxIdCheckResponse
     * @param $taxId
     * @return TaxIdCheckResponseInterface
     */
    private function validateFormat($taxIdCheckResponse, $taxId): TaxIdCheckResponseInterface
    {
        if (($taxId === null || strlen($taxId) < 1)) {
            $taxIdCheckResponse->setRequestMessage(__('You didn\'t supply a VAT number to check.'));
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(true);
            return $taxIdCheckResponse;
        }
        if (preg_match("/^(GB)?([0-9]{9}([0-9]{3})?|(GD|HA)[0-9]{3})$/i", $taxId)) {
            $taxIdCheckResponse->setIsValid(true);
            $taxIdCheckResponse->setRequestSuccess(true);
            $taxIdCheckResponse->setRequestMessage(__('VAT number is the correct format.'));
        } else {
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(true);
            $taxIdCheckResponse->setRequestMessage(__('VAT number is not the correct format.'));
        }
        return $taxIdCheckResponse;
    }

    /**
     * Perform online validation of the Tax Identifier
     *
     * @param $taxIdCheckResponse
     * @param $taxId
     * @return TaxIdCheckResponseInterface
     */
    private function validateOnline($taxIdCheckResponse, $taxId): TaxIdCheckResponseInterface
    {
        $registrationNumber = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationnumber",
            ScopeInterface::SCOPE_STORE
        );
        if (empty($registrationNumber)) {
            $this->logger->critical(
                "Gw/AutoCustomerGroupUk/Model/TaxScheme::checkTaxId() : UKVat Registration Number not set."
            );
            $taxIdCheckResponse->setRequestMessage(__('UKVat Registration Number not set.'));
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(false);
            return $taxIdCheckResponse;
        }
        if ($this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/environment",
                ScopeInterface::SCOPE_STORE
            ) == Environment::ENVIRONMENT_PRODUCTION) {
            $baseUrl = "https://api.service.hmrc.gov.uk";
        } else {
            $baseUrl = "https://test-api.service.hmrc.gov.uk";
        }
        try {
            $client = $this->clientFactory->create();
            $response = $client->request(
                Request::HTTP_METHOD_GET,
                    $baseUrl . "/organisations/vat/check-vat-number/lookup/" .
                str_replace([' ', '-', 'GB'], ['', '', ''], $taxId) . "/" .
                str_replace([' ', '-', 'GB'], ['', '', ''], $registrationNumber),
                [
                    'headers' => [
                        'Accept' => "application/vnd.hmrc.1.0+json"
                    ]
                ]
            );
            $responseBody = $response->getBody();
            $vatRegistration = $this->serializer->unserialize($responseBody->getContents());
            $taxIdCheckResponse->setIsValid(true);
            $taxIdCheckResponse->setRequestSuccess(true);
            $taxIdCheckResponse->setRequestDate($vatRegistration['processingDate']);
            if (array_key_exists('consultationNumber', $vatRegistration)) {
                $taxIdCheckResponse->setRequestIdentifier($vatRegistration['consultationNumber']);
            }
            if ($taxIdCheckResponse->getIsValid()) {
                $taxIdCheckResponse->setRequestMessage(__('VAT Number validated with HMRC.'));
            } else {
                $taxIdCheckResponse->setRequestMessage(__('Please enter a valid VAT number including country code.'));
            }
        } catch (BadResponseException $e) {
            switch ($e->getCode()) {
                case 404:
                    $taxIdCheckResponse->setIsValid(false);
                    $taxIdCheckResponse->setRequestSuccess(true);
                    $taxIdCheckResponse->setRequestMessage(__('Please enter a valid VAT number.'));
                    break;
                default:
                    $taxIdCheckResponse->setRequestSuccess(false);
                    $taxIdCheckResponse->setIsValid(false);
                    $taxIdCheckResponse->setRequestMessage(__('There was an error checking the VAT number.'));
                    $this->logger->error(
                        "Gw/AutoCustomerGroupUk/Model/TaxScheme::checkTaxId() : UKVat Error received from " .
                        "HMRC. " . $e->getCode()
                    );
                    break;
            }
        }
        return $taxIdCheckResponse;
    }

    /**
     * Get the scheme name
     *
     * @return string
     */
    public function getSchemeName(): string
    {
        return __("United Kingdom & Isle of Man VAT Scheme");
    }

    /**
     * Get the scheme code
     *
     * @return string
     */
    public function getSchemeId(): string
    {
        return self::CODE;
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getFrontEndPrompt(?int $storeId): ?string
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/frontendprompt",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return string
     */
    public function getSchemeCurrencyCode(): string
    {
        return self::SCHEME_CURRENCY;
    }

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getThresholdInSchemeCurrency(?int $storeId): float
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/importthreshold",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getSchemeRegistrationNumber(?int $storeId): ?string
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationnumber",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return array
     */
    public function getSchemeCountries(): array
    {
        return self::SCHEME_COUNTRIES;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            "autocustomergroup/" . self::CODE . "/enabled",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getSchemeExchangeRate(?int $storeId): float
    {
        if ($this->scopeConfig->isSetFlag(
            "autocustomergroup/" . self::CODE . '/usemagentoexchangerate',
            ScopeInterface::SCOPE_STORE,
            $storeId
        )) {
            $websiteBaseCurrency = $this->scopeConfig->getValue(
                Currency::XML_PATH_CURRENCY_BASE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $exchangerate = $this->currencyFactory
                ->create()
                ->load($this->getSchemeCurrencyCode())
                ->getAnyRate($websiteBaseCurrency);
            if (!$exchangerate) {
                $this->logger->critical(
                    "Gw/AutoCustomerGroupUk/Model/TaxScheme::getSchemeExchangeRate() : " .
                    "No Magento Exchange Rate configured for " . self::SCHEME_CURRENCY . " to " .
                    $websiteBaseCurrency . ". Using 1.0"
                );
                $exchangerate = 1.0;
            }
            return (float)$exchangerate;
        }
        return (float)$this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/exchangerate",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
