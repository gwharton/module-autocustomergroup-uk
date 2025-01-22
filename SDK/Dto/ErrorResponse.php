<?php
namespace Gw\AutoCustomerGroupUk\SDK\Dto;

class ErrorResponse
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
