<?php
namespace Gw\AutoCustomerGroupUk\SDK\Requests;

use Exception;
use Gw\AutoCustomerGroupUk\SDK\Responses\ErrorResponse;
use Gw\AutoCustomerGroupUk\SDK\Responses\GetVATRegistrationWithReferenceResponse;
use JsonMapper;
use Saloon\Enums\Method;
use Saloon\Http\Response;

class GetVATRegistrationWithReference extends BaseRequest
{
    protected Method $method = Method::GET;

    /**
     * @param string $targetVrn
     * @param string $requesterVrn
     */
    public function __construct(
        public string $targetVrn,
        public string $requesterVrn
    ) {}

    public function resolveEndpoint(): string
    {
        return "/organisations/vat/check-vat-number/lookup/{$this->targetVrn}/{$this->requesterVrn}";
    }

    public function createDtoFromResponse(Response $response): GetVATRegistrationWithReferenceResponse|ErrorResponse
    {
        $status = $response->status();
        $responseClass = match ($status) {
            200 => GetVATRegistrationWithReferenceResponse::class,
            400, 401, 403, 404, 500 => ErrorResponse::class,
            default => throw new Exception("Unhandled response status: {$status}")
        };
        return (new JsonMapper)->map(json_decode($response->body()), $responseClass);
    }
}
