<?php
namespace Gw\AutoCustomerGroupUk\SDK\Requests;

use Exception;
use Gw\AutoCustomerGroupUk\SDK\Responses\ErrorResponse;
use Gw\AutoCustomerGroupUk\SDK\Responses\GetVATRegistrationResponse;
use JsonMapper;
use Saloon\Enums\Method;
use Saloon\Http\Response;

class GetVATRegistration extends BaseRequest
{
    protected Method $method = Method::GET;

    /**
     * @param string $targetVrn
     */
    public function __construct(
        public string $targetVrn
    ) {}

    public function resolveEndpoint(): string
    {
        return "/organisations/vat/check-vat-number/lookup/{$this->targetVrn}";
    }

    public function createDtoFromResponse(Response $response): GetVATRegistrationResponse|ErrorResponse
    {
        $status = $response->status();
        $responseClass = match ($status) {
            200 => GetVATRegistrationResponse::class,
            400, 401, 403, 404, 500 => ErrorResponse::class,
            default => throw new Exception("Unhandled response status: {$status}")
        };
        return (new JsonMapper)->map(json_decode($response->body()), $responseClass);
    }
}
