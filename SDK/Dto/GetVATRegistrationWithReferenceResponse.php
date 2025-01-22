<?php
namespace Gw\AutoCustomerGroupUk\SDK\Dto;

class GetVATRegistrationWithReferenceResponse
{
    /**
     * @param Target $target
     * @param string $requester
     * @param string $consultationNumber
     * @param string $processingDate
     */
    public function __construct(
        public Target $target,
        public string $requester,
        public string $consultationNumber,
        public string $processingDate
    ) {}
}
