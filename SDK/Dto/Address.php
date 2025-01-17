<?php
namespace Gw\AutoCustomerGroupUk\SDK\Dto;

class Address
{
    /**
     * @param string $line1
     * @param string $postcode
     * @param string $countryCode
     */
    public function __construct(
        public string $line1,
        public string $postcode,
        public string $countryCode
    ) {}
}
