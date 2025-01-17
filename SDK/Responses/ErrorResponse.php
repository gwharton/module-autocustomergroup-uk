<?php
namespace Gw\AutoCustomerGroupUk\SDK\Responses;

use Saloon\Http\Response;

class ErrorResponse extends Response
{
    /**
     * @param string $code
     * @param string $message
     */
    public function __construct(
        public string $code,
        public string $message
    ) {}
}
