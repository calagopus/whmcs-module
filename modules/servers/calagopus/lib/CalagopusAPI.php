<?php

namespace WHMCS\Module\Server\Calagopus;

class CalagopusAPI
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Make a GET request to the Calagopus API.
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, [], $query);
    }

    /**
     * Make a POST request to the Calagopus API.
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Make a PATCH request to the Calagopus API.
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * Make a PUT request to the Calagopus API.
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * Make a DELETE request to the Calagopus API.
     */
    public function delete(string $endpoint, array $data = []): array
    {
        return $this->request('DELETE', $endpoint, $data);
    }

    /**
     * Perform the HTTP request via cURL.
     */
    private function request(string $method, string $endpoint, array $data = [], array $query = []): array
    {
        $url = $this->baseUrl . $endpoint;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $requestInfo = ['method' => $method, 'url' => $url];
        if (!empty($data)) {
            $requestInfo['body'] = $data;
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if (!empty($data) && in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            \logModuleCall('calagopus', $method . ' ' . $endpoint, $requestInfo, 'cURL Error: ' . $error, null, [$this->apiKey]);
            throw new \Exception('cURL Error: ' . $error);
        }

        $decoded = json_decode($response, true);

        \logModuleCall('calagopus', $method . ' ' . $endpoint, $requestInfo, $response, $decoded, [$this->apiKey]);

        if ($httpCode >= 400) {
            $errorMsg = 'API Error (HTTP ' . $httpCode . ')';
            if (isset($decoded['errors']) && is_array($decoded['errors'])) {
                $errorMsg .= ': ' . implode(', ', $decoded['errors']);
            }
            throw new \Exception($errorMsg);
        }

        return $decoded ?? [];
    }

    /**
     * Find a user by their external ID (billing system user ID).
     */
    public function getUserByExternalId(string $externalId): ?array
    {
        try {
            $response = $this->get('/api/admin/users/external/' . urlencode($externalId));
            return $response['user'] ?? null;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Create a new panel user.
     */
    public function createUser(array $data): array
    {
        $response = $this->post('/api/admin/users', $data);
        return $response['user'];
    }

    /**
     * Search users by email or username.
     */
    public function searchUsers(string $search): array
    {
        $response = $this->get('/api/admin/users', [
            'page' => 1,
            'per_page' => 10,
            'search' => $search,
        ]);
        return $response['users']['data'] ?? [];
    }

    /**
     * Update a user by UUID.
     */
    public function updateUser(string $userUuid, array $data): array
    {
        return $this->patch('/api/admin/users/' . $userUuid, $data);
    }

    /**
     * Find or create a user, keyed by external ID.
     *
     * Handles the case where a user with the same email/username already
     * exists on the panel (409 conflict) by searching for the existing
     * user and linking them via external_id.
     */
    public function findOrCreateUser(string $externalId, string $email, string $firstName, string $lastName, string $username): array
    {
        // 1. Try lookup by external ID first
        $existing = $this->getUserByExternalId($externalId);
        if ($existing) {
            return $existing;
        }

        // 2. Try creating the user
        try {
            return $this->createUser([
                'external_id' => $externalId,
                'username' => $username,
                'email' => $email,
                'name_first' => $firstName,
                'name_last' => $lastName,
                'admin' => false,
                'send_email' => true,
                'language' => 'en',
            ]);
        } catch (\Exception $e) {
            // Only handle 409 conflicts — rethrow anything else
            if (!str_contains($e->getMessage(), '409')) {
                throw $e;
            }
        }

        // 3. User already exists on the panel — find them by email
        $matches = $this->searchUsers($email);
        $matched = null;

        foreach ($matches as $user) {
            if (strcasecmp($user['email'] ?? '', $email) === 0) {
                $matched = $user;
                break;
            }
        }

        // 4. If no email match, try by username
        if (!$matched) {
            $matches = $this->searchUsers($username);
            foreach ($matches as $user) {
                if (strcasecmp($user['username'] ?? '', $username) === 0) {
                    $matched = $user;
                    break;
                }
            }
        }

        if (!$matched) {
            throw new \Exception('User with this email/username already exists on the panel but could not be found via search.');
        }

        // 5. Link the existing panel user to this billing account by setting external_id
        $this->updateUser($matched['uuid'], [
            'external_id' => $externalId,
        ]);

        return $matched;
    }

    /**
     * Create a server with explicit node and allocations.
     */
    public function createServer(array $data): array
    {
        $response = $this->post('/api/admin/servers', $data);
        return $response['server'];
    }

    /**
     * Deploy a server with automatic node/allocation selection.
     */
    public function deployServer(array $data): array
    {
        $response = $this->post('/api/admin/servers/deploy', $data);
        return $response['server'];
    }

    /**
     * Get server details by UUID.
     */
    public function getServer(string $serverUuid): array
    {
        $response = $this->get('/api/admin/servers/' . $serverUuid);
        return $response['server'];
    }

    /**
     * Get server details by external ID.
     */
    public function getServerByExternalId(string $externalId): ?array
    {
        try {
            $response = $this->get('/api/admin/servers/external/' . urlencode($externalId));
            return $response['server'] ?? null;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Update a server (name, limits, suspend, etc.).
     */
    public function updateServer(string $serverUuid, array $data): array
    {
        return $this->patch('/api/admin/servers/' . $serverUuid, $data);
    }

    /**
     * Suspend a server.
     */
    public function suspendServer(string $serverUuid): array
    {
        return $this->updateServer($serverUuid, ['suspended' => true]);
    }

    /**
     * Unsuspend a server.
     */
    public function unsuspendServer(string $serverUuid): array
    {
        return $this->updateServer($serverUuid, ['suspended' => false]);
    }

    /**
     * Delete a server.
     */
    public function deleteServer(string $serverUuid, bool $force = false, bool $deleteBackups = true): array
    {
        return $this->delete('/api/admin/servers/' . $serverUuid, [
            'force' => $force,
            'delete_backups' => $deleteBackups,
        ]);
    }

    /**
     * List all locations.
     */
    public function getLocations(): array
    {
        $response = $this->get('/api/admin/locations', ['page' => 1, 'per_page' => 100]);
        return $response['locations']['data'] ?? [];
    }

    /**
     * List all nodes.
     */
    public function getNodes(): array
    {
        $response = $this->get('/api/admin/nodes', ['page' => 1, 'per_page' => 100]);
        return $response['nodes']['data'] ?? [];
    }

    /**
     * List all nests with their eggs.
     */
    public function getNestsWithEggs(): array
    {
        $response = $this->get('/api/admin/nests/eggs');
        return $response['nests'] ?? [];
    }

    /**
     * List eggs for a specific nest.
     */
    public function getEggs(string $nestUuid): array
    {
        $response = $this->get('/api/admin/nests/' . $nestUuid . '/eggs', [
            'page' => 1,
            'per_page' => 100,
        ]);
        return $response['eggs']['data'] ?? [];
    }

    /**
     * Get egg variables.
     */
    public function getEggVariables(string $nestUuid, string $eggUuid): array
    {
        $response = $this->get('/api/admin/nests/' . $nestUuid . '/eggs/' . $eggUuid . '/variables');
        return $response['variables'] ?? [];
    }

    /**
     * List available allocations on a node.
     */
    public function getAvailableAllocations(string $nodeUuid): array
    {
        $response = $this->get('/api/admin/nodes/' . $nodeUuid . '/allocations/available', [
            'page' => 1,
            'per_page' => 100,
        ]);
        return $response['allocations']['data'] ?? [];
    }

    /**
     * List nodes in a location.
     */
    public function getNodesByLocation(string $locationUuid): array
    {
        $response = $this->get('/api/admin/locations/' . $locationUuid . '/nodes', [
            'page' => 1,
            'per_page' => 100,
        ]);
        return $response['nodes']['data'] ?? [];
    }

    /**
     * Get server variables.
     */
    public function getServerVariables(string $serverUuid): array
    {
        $response = $this->get('/api/admin/servers/' . $serverUuid . '/variables');
        return $response['variables'] ?? [];
    }

    /**
     * Update server variables.
     */
    public function updateServerVariables(string $serverUuid, array $variables): array
    {
        return $this->put('/api/admin/servers/' . $serverUuid . '/variables', [
            'variables' => $variables,
        ]);
    }

    /**
     * Get a single egg's details.
     */
    public function getEgg(string $nestUuid, string $eggUuid): array
    {
        $response = $this->get('/api/admin/nests/' . $nestUuid . '/eggs/' . $eggUuid);
        return $response['egg'];
    }

    /**
     * Get the panel URL for a server (for "Go to Server" button).
     */
    public function getServerUrl(string $serverUuid): string
    {
        return $this->baseUrl . '/server/' . $serverUuid;
    }
}
