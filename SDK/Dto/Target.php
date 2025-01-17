<?php
namespace Gw\AutoCustomerGroupUk\SDK\Dto;

class Target
{
    /**
     * @param string $name
     * @param string $vatNumber
     * @param \Gw\AutoCustomerGroupUk\SDK\Dto\Address $address
     */
    public function __construct(
        public string $name,
        public string $vatNumber,
        public Address $address
    ) {}
}
