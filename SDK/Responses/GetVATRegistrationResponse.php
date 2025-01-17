<?php
namespace Gw\AutoCustomerGroupUk\SDK\Responses;

use Gw\AutoCustomerGroupUk\SDK\Dto\Target;
use Saloon\Http\Response;

class GetVATRegistrationResponse extends Response
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
