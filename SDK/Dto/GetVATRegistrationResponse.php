<?php
namespace Gw\AutoCustomerGroupUk\SDK\Dto;

class GetVATRegistrationResponse
{
    /**
     * @param \Gw\AutoCustomerGroupUk\SDK\Dto\Target $code
     * @param string $message
     */
    public function __construct(
        public Target $target,
        public string $processingDate
    ) {}
}
