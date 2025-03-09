namespace App\Services;

use GuzzleHttp\Client;
use Firebase\JWT\JWT;

class FirebaseService
{
    private $apiKey;
    private $projectId;
    private $databaseUrl;
    private $httpClient;

    public function __construct()
    {
        $this->apiKey = env('FIREBASE_API_KEY');
        $this->projectId = env('FIREBASE_PROJECT_ID');
        $this->databaseUrl = env('FIREBASE_DATABASE_URL');
        $this->httpClient = new Client();
    }

    public function createTenantDatabase($tenantId)
    {
        $databasePath = "{$this->databaseUrl}/tenants/{$tenantId}.json";

        $response = $this->httpClient->request('PUT', $databasePath, [
            'json' => ['created_at' => now()],
            'query' => ['auth' => $this->apiKey]
        ]);

        return json_decode($response->getBody(), true);
    }
}
