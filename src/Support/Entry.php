<?php

namespace MacsiDigital\Zoom\Support;

use MacsiDigital\API\Support\Authentication\JWT;
use MacsiDigital\API\Support\Entry as ApiEntry;
use MacsiDigital\Zoom\Facades\Client;

class Entry extends ApiEntry
{
    protected $modelNamespace = '\MacsiDigital\Zoom\\';

    protected $pageField = 'page_number';

    protected $maxQueries = '5';

    protected $apiKey = null;

    protected $apiSecret = null;

    protected $tokenLife = null;

    protected $baseUrl = null;
    
    protected $filePath = '';
    
    protected $guzzleClient = null;

    // Amount of pagination results per page by default, leave blank if should not paginate
    // Without pagination rate limits could be hit
    protected $defaultPaginationRecords = '30';

    // Max and Min pagination records per page, will vary by API server
    protected $maxPaginationRecords = '300';

    protected $resultsPageField = 'page_number';
    protected $resultsTotalPagesField = 'page_count';
    protected $resultsPageSizeField = 'page_size';
    protected $resultsTotalRecordsField = 'total_records';

    protected $allowedOperands = ['='];

    /**
     * Entry constructor.
     * @param $apiKey
     * @param $apiSecret
     * @param $tokenLife
     * @param $maxQueries
     * @param $baseUrl
     */
    public function __construct($apiKey = null, $apiSecret = null, $tokenLife = null, $maxQueries = null, $baseUrl = null)
    {
        $this->apiKey = $apiKey ? $apiKey : config('zoom.api_key');
        $this->apiSecret = $apiSecret ? $apiSecret : config('zoom.api_secret');
        $this->tokenLife = $tokenLife ? $tokenLife : config('zoom.token_life');
        $this->maxQueries = $maxQueries ? $maxQueries : (config('zoom.max_api_calls_per_request') ? config('zoom.max_api_calls_per_request') : $this->maxQueries);
        $this->baseUrl = $baseUrl ? $baseUrl : config('zoom.base_url');
        $this->guzzleClient = new GuzzleClient();
        $this->filePath = config('zoom.token_json');
    }
    
    public function newRequest()
    {
        if (config('zoom.authentication_method') == 'jwt') {
            return $this->jwtRequest();
        } elseif (config('zoom.authentication_method') == 'oauth2') {
            return $this->oauth2Request();
        } else {
            throw new \Exception('Wrong driver!');
        }
    }

    public function jwtRequest()
    {
        $jwtToken = JWT::generateToken(['iss' => $this->apiKey, 'exp' => time() + $this->tokenLife], $this->apiSecret);

        return Client::baseUrl($this->baseUrl)->withToken($jwtToken);
    }

    public function oauth2Request()
    {
        try {
            $this->checkTokenExpiration();
        } catch (\Exception $exception) {
            $response = $this->generateOAuthToken();
            $response_token = json_decode($response->getBody()->getContents(), true);
            $this->createFile(json_encode($response_token));
        }

        return Client::baseUrl($this->baseUrl)->withToken($this->getAccessToken());
    }
    
    private function generateOAuthToken(): Response
    {
        return $this->guzzleClient->post(config('zoom.oauth_token_url'), $this->getRequestHeader());
    }
    
    private function createFile($token): void
    {
        $tokenPath = $this->getUserTokenFilePath();

        $this->createFolder($tokenPath);

        file_put_contents($tokenPath, $token);
    }
    
    private function hasUserTokenFile(): bool
    {
        return file_exists($this->getUserTokenFilePath());
    }
    
    private function createFolder($tokenPath): void
    {
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
    }
    
    private function getUserTokenFilePath(): string
    {
        return storage_path($this->filePath);
    }

    private function checkTokenExpiration(): void
    {
        $this->guzzleClient->get($this->baseUrl . 'users', [
            "headers" => [
                "Authorization" => "Bearer " . $this->getAccessToken()
            ],
        ]);
    }
    
    private function getRequestHeader(): array
    {
        return [
            "headers"     => [
                "Authorization" => "Basic " . base64_encode($this->apiKey . ':' . $this->apiSecret)
            ],
            'form_params' => [
                "grant_type" => "account_credentials",
                "account_id" => config('zoom.account_id'),
            ],
        ];
    }
    
    private function getAccessToken(): string
    {
        $token = Arr::get($this->getJsonFile(), 'access_token');

        if (!$token) {
            throw new InvalidArgumentException("Invalid token format");
        }

        return $token;
    }

    private function getJsonFile(): ?array
    {
        return json_decode(file_get_contents($this->getUserTokenFilePath()), true);
    }
}
