<?php
namespace Gw\AutoCustomerGroupUk\SDK;

use Gw\AutoCustomerGroup\Model\Config\Source\Environment;
use Gw\AutoCustomerGroupUk\SDK\Requests\GetVATRegistrationWithReferenceRequest;
use Gw\AutoCustomerGroupUk\SDK\Requests\GetVATRegistrationRequest;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\OAuth2\ClientCredentialsGrant;

class HMRCConnector extends Connector
{
    private const string CACHE_TAG = "GW_AUTOCUSTOMERGROUP_UK_TOKEN_CACHE";

    use ClientCredentialsGrant {
        getAccessToken as protected originalGetAccessToken;
    }

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private CacheInterface $cache
    ) {}

    protected function getAccessToken()
    {
        //If the token is in cache, use it
        $data = $this->cache->load(
            self::CACHE_TAG
        );
        if ($data) {
            return unserialize($data, ['allowed_classes' => true]);
        }
        //Get new token
        $accessTokenAuthenticator = $this->originalGetAccessToken();
        //Save token to cache
        $this->cache->save(
            serialize($accessTokenAuthenticator),
            self::CACHE_TAG,
            [self::CACHE_TAG],
            $accessTokenAuthenticator->getExpiresAt()->getTimestamp() - time()
        );
        return $accessTokenAuthenticator;
    }

    protected function defaultOauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId(
                $this->scopeConfig->getValue(
                    'autocustomergroup/ukvat/clientid',
                    ScopeInterface::SCOPE_STORE
                )
            )->setClientSecret(
                $this->scopeConfig->getValue(
                    'autocustomergroup/ukvat/clientsecret',
                    ScopeInterface::SCOPE_STORE
                )

            )
            ->setDefaultScopes(['read:vat'])
            ->setTokenEndpoint('/oauth/token');
    }

    public function resolveBaseUrl(): string
    {
        if ($this->scopeConfig->getValue(
            'autocustomergroup/ukvat/environment',
            ScopeInterface::SCOPE_STORE
        ) === Environment::ENVIRONMENT_SANDBOX) {
            return ("https://test-api.service.hmrc.gov.uk");
        } else {
            return ("https://api.service.hmrc.gov.uk");
        }
    }

    protected function defaultHeaders(): array
    {
        return [
            'user-agent' => 'GrahamWhartonAutoCustomerGroupUK/1.0 graham@lubefinder.com',
            'accept' => 'application/vnd.hmrc.2.0+json'
        ];
    }

    public function getVATRegistration(
        string $targetVrn
    ): Response {
        $authenticator = $this->getAccessToken();
        $this->authenticate($authenticator);
        $request = new GetVATRegistrationRequest(
            $targetVrn
        );
        return $this->send($request);
    }

    public function getVATRegistrationWithReference(
        string $targetVrn,
        string $requesterVrn
    ): Response {
        $authenticator = $this->getAccessToken();
        $this->authenticate($authenticator);
        $request = new GetVATRegistrationWithReferenceRequest(
            $targetVrn,
            $requesterVrn
        );
        return $this->send($request);
    }
}
