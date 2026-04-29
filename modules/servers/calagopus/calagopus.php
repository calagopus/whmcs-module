<?php

/**
 * Calagopus WHMCS Provisioning Module
 *
 * A server provisioning module for the Calagopus game server panel.
 * Similar to Pterodactyl but with support for custom/extension-added feature limits.
 *
 * @see https://calagopus.com
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

require_once __DIR__ . '/lib/CalagopusAPI.php';

use WHMCS\Module\Server\Calagopus\CalagopusAPI;

/**
 * Build a CalagopusAPI instance from WHMCS server params.
 */
function calagopus_API(array $params): CalagopusAPI
{
    $url = ($params['serversecure'] ? 'https' : 'http') . '://' . $params['serverhostname'];
    if (!empty($params['serverport'])) {
        $url .= ':' . $params['serverport'];
    }

    return new CalagopusAPI($url, $params['serverpassword']);
}

/**
 * Parse the custom feature limits from the config field.
 * Format: "key1:value1,key2:value2" e.g. "plugins:5,worlds:3"
 */
function calagopus_ParseCustomFeatureLimits(string $raw): array
{
    $limits = [];
    $raw = trim($raw);
    if (empty($raw)) {
        return $limits;
    }

    $pairs = explode(',', $raw);
    foreach ($pairs as $pair) {
        $pair = trim($pair);
        if (str_contains($pair, ':')) {
            [$key, $value] = explode(':', $pair, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key !== '' && is_numeric($value)) {
                $limits[$key] = (int) $value;
            }
        }
    }

    return $limits;
}

/**
 * Parse egg environment variables from the config field.
 * Format: "VAR_NAME=value" per line.
 */
function calagopus_ParseVariables(string $raw): array
{
    $variables = [];
    $raw = trim($raw);
    if (empty($raw)) {
        return $variables;
    }

    $lines = explode("\n", $raw);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '' && str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key !== '') {
                $variables[] = [
                    'env_variable' => $key,
                    'value' => $value,
                ];
            }
        }
    }

    return $variables;
}

/**
 * Generate a panel-safe username from client details.
 */
function calagopus_GenerateUsername(array $params): string
{
    $base = preg_replace('/[^a-zA-Z0-9_]/', '', $params['clientsdetails']['firstname'] . $params['clientsdetails']['lastname']);

    if (strlen($base) < 3) {
        $base = 'user';
    }

    $userId = (string)$params['clientsdetails']['userid'];

    $maxBaseLength = max(0, 14 - strlen($userId));
    $truncatedBase = substr($base, 0, $maxBaseLength);

    return strtolower($truncatedBase) . '_' . $userId;
}

function calagopus_MetaData(): array
{
    return [
        'DisplayName' => 'Calagopus',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
    ];
}

function calagopus_ConfigOptions(): array
{
    return [
        'nest_uuid' => [
            'FriendlyName' => 'Nest UUID',
            'Type' => 'text',
            'Size' => 40,
            'Description' => 'UUID of the nest containing the egg.',
        ],
        'egg_uuid' => [
            'FriendlyName' => 'Egg UUID',
            'Type' => 'text',
            'Size' => 40,
            'Description' => 'UUID of the egg to use for new servers.',
        ],
        'node_uuid' => [
            'FriendlyName' => 'Node UUID (optional)',
            'Type' => 'text',
            'Size' => 40,
            'Description' => 'Leave blank to use deploy mode with location_uuids.',
        ],
        'location_uuids' => [
            'FriendlyName' => 'Location UUIDs (deploy mode)',
            'Type' => 'text',
            'Size' => 80,
            'Description' => 'Comma-separated location UUIDs for auto-deploy. Used if Node UUID is blank.',
        ],
        'memory' => [
            'FriendlyName' => 'Memory (MB)',
            'Type' => 'text',
            'Size' => 10,
            'Default' => '1024',
        ],
        'swap' => [
            'FriendlyName' => 'Swap (MB)',
            'Type' => 'text',
            'Size' => 10,
            'Default' => '0',
        ],
        'disk' => [
            'FriendlyName' => 'Disk (MB)',
            'Type' => 'text',
            'Size' => 10,
            'Default' => '10240',
        ],
        'cpu' => [
            'FriendlyName' => 'CPU Limit (%)',
            'Type' => 'text',
            'Size' => 10,
            'Default' => '100',
        ],
        'memory_overhead' => [
            'FriendlyName' => 'Memory Overhead (MB)',
            'Type' => 'text',
            'Size' => 10,
            'Default' => '0',
        ],
        'io_weight' => [
            'FriendlyName' => 'IO Weight (10-1000, blank=default)',
            'Type' => 'text',
            'Size' => 10,
            'Default' => '',
        ],
        'allocations_limit' => [
            'FriendlyName' => 'Allocation Limit',
            'Type' => 'text',
            'Size' => 5,
            'Default' => '1',
        ],
        'database_limit' => [
            'FriendlyName' => 'Database Limit',
            'Type' => 'text',
            'Size' => 5,
            'Default' => '0',
        ],
        'backup_limit' => [
            'FriendlyName' => 'Backup Limit',
            'Type' => 'text',
            'Size' => 5,
            'Default' => '0',
        ],
        'schedule_limit' => [
            'FriendlyName' => 'Schedule Limit',
            'Type' => 'text',
            'Size' => 5,
            'Default' => '0',
        ],
        'custom_feature_limits' => [
            'FriendlyName' => 'Custom Feature Limits',
            'Type' => 'textarea',
            'Rows' => 3,
            'Cols' => 40,
            'Description' => 'Extension-added feature limits. Format: key:value per entry, comma-separated. E.g. "plugins:5,worlds:3"',
        ],
        'docker_image' => [
            'FriendlyName' => 'Docker Image (optional)',
            'Type' => 'text',
            'Size' => 60,
            'Description' => 'Override the egg default docker image. Leave blank for egg default.',
        ],
        'startup_command' => [
            'FriendlyName' => 'Startup Command (optional)',
            'Type' => 'text',
            'Size' => 80,
            'Description' => 'Override the egg default startup command. Leave blank for egg default.',
        ],
        'server_name_prefix' => [
            'FriendlyName' => 'Server Name Prefix',
            'Type' => 'text',
            'Size' => 20,
            'Default' => '',
            'Description' => 'Prefix for auto-generated server names. E.g. "MC-" → "MC-12345"',
        ],
        'variables' => [
            'FriendlyName' => 'Egg Variables',
            'Type' => 'textarea',
            'Rows' => 5,
            'Cols' => 60,
            'Description' => 'One per line: VAR_NAME=value',
        ],
        'skip_installer' => [
            'FriendlyName' => 'Skip Installer',
            'Type' => 'yesno',
            'Default' => '',
        ],
        'start_on_completion' => [
            'FriendlyName' => 'Start on Completion',
            'Type' => 'yesno',
            'Default' => 'on',
        ],
        'backup_configuration_uuid' => [
            'FriendlyName' => 'Backup Configuration UUID (optional)',
            'Type' => 'text',
            'Size' => 40,
        ],
        'hugepages_passthrough' => [
            'FriendlyName' => 'Hugepages Passthrough',
            'Type' => 'yesno',
            'Default' => '',
            'Description' => 'Mount /dev/hugepages into the container.',
        ],
        'kvm_passthrough' => [
            'FriendlyName' => 'KVM Passthrough',
            'Type' => 'yesno',
            'Default' => '',
            'Description' => 'Allow access to /dev/kvm inside the container.',
        ],
        'pinned_cpus' => [
            'FriendlyName' => 'Pinned CPUs (optional)',
            'Type' => 'text',
            'Size' => 30,
            'Default' => '',
            'Description' => 'Comma-separated CPU core IDs to pin. E.g. "0,1,2"',
        ],
    ];
}

function calagopus_TestConnection(array $params): array
{
    try {
        $api = calagopus_API($params);
        $api->getLocations();
        return ['success' => true, 'error' => ''];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

//  1=nest_uuid  2=egg_uuid  3=node_uuid  4=location_uuids
//  5=memory  6=swap  7=disk  8=cpu  9=memory_overhead  10=io_weight
// 11=allocations_limit  12=database_limit  13=backup_limit  14=schedule_limit
// 15=custom_feature_limits  16=docker_image  17=startup_command
// 18=server_name_prefix  19=variables  20=skip_installer
// 21=start_on_completion  22=backup_configuration_uuid
// 23=hugepages_passthrough  24=kvm_passthrough  25=pinned_cpus

function calagopus_Cfg(array $params, int $n, $default = ''): string
{
    return trim($params['configoption' . $n] ?? $default);
}

// ─── Create Account (Provision Server) ────────────────────────────────────────

function calagopus_CreateAccount(array $params): string
{
    try {
        $api = calagopus_API($params);

        $externalUserId = (string) $params['clientsdetails']['userid'];
        $username = calagopus_GenerateUsername($params);

        $user = $api->findOrCreateUser(
            $externalUserId,
            $params['clientsdetails']['email'],
            $params['clientsdetails']['firstname'],
            $params['clientsdetails']['lastname'],
            $username
        );

        $nestUuid  = calagopus_Cfg($params, 1);
        $eggUuid   = calagopus_Cfg($params, 2);
        $nodeUuid  = calagopus_Cfg($params, 3);
        $locationUuids = calagopus_Cfg($params, 4);

        $egg = $api->getEgg($nestUuid, $eggUuid);

        $dockerImage = calagopus_Cfg($params, 16) ?: (array_values($egg['docker_images'])[0] ?? '');
        $startup = calagopus_Cfg($params, 17) ?: (isset($egg['startup'])
            ? $egg['startup'] : (isset($egg['startup_commands']['Default'])
                ? $egg['startup_commands']['Default']
                : (array_values($egg['startup_commands'])[0] ?? '')));

        $prefix = calagopus_Cfg($params, 18);
        $serverName = ($prefix ? $prefix : 'Server-') . $params['serviceid'];

        $featureLimits = [
            'allocations' => (int) calagopus_Cfg($params, 11, '1'),
            'databases'   => (int) calagopus_Cfg($params, 12, '0'),
            'backups'     => (int) calagopus_Cfg($params, 13, '0'),
            'schedules'   => (int) calagopus_Cfg($params, 14, '0'),
        ];

        $customLimits = calagopus_ParseCustomFeatureLimits(calagopus_Cfg($params, 15));
        $featureLimits = array_merge($featureLimits, $customLimits);

        $ioWeightRaw = calagopus_Cfg($params, 10);
        $ioWeight = $ioWeightRaw !== '' ? (int) $ioWeightRaw : null;

        $pinnedCpusRaw = calagopus_Cfg($params, 25);
        $pinnedCpus = [];
        if ($pinnedCpusRaw !== '') {
            $pinnedCpus = array_map('intval', array_filter(array_map('trim', explode(',', $pinnedCpusRaw)), 'is_numeric'));
        }

        $serverPayload = [
            'owner_uuid' => $user['uuid'],
            'egg_uuid' => $eggUuid,
            'start_on_completion' => calagopus_Cfg($params, 21, 'on') === 'on',
            'skip_installer' => calagopus_Cfg($params, 20) === 'on',
            'external_id' => (string) $params['serviceid'],
            'name' => $serverName,
            'limits' => [
                'cpu'             => (int) calagopus_Cfg($params, 8, '100'),
                'memory'          => (int) calagopus_Cfg($params, 5, '1024'),
                'memory_overhead' => (int) calagopus_Cfg($params, 9, '0'),
                'swap'            => (int) calagopus_Cfg($params, 6, '0'),
                'disk'            => (int) calagopus_Cfg($params, 7, '10240'),
            ],
            'pinned_cpus' => $pinnedCpus,
            'startup' => $startup,
            'image' => $dockerImage,
            'hugepages_passthrough_enabled' => calagopus_Cfg($params, 23) === 'on',
            'kvm_passthrough_enabled' => calagopus_Cfg($params, 24) === 'on',
            'feature_limits' => $featureLimits,
            'variables' => calagopus_ParseVariables(calagopus_Cfg($params, 19)),
        ];

        if ($ioWeight !== null) {
            $serverPayload['limits']['io_weight'] = $ioWeight;
        }

        $backupConfigUuid = calagopus_Cfg($params, 22);
        if ($backupConfigUuid) {
            $serverPayload['backup_configuration_uuid'] = $backupConfigUuid;
        }

        // Deploy mode (auto) vs explicit node
        if ($nodeUuid) {
            $allocations = $api->getAvailableAllocations($nodeUuid);
            if (empty($allocations)) {
                return 'No available allocations on the selected node.';
            }

            $serverPayload['node_uuid'] = $nodeUuid;
            $serverPayload['allocation_uuid'] = $allocations[0]['uuid'];

            $server = $api->createServer($serverPayload);
        } else {
            // Deploy mode: auto-select node and allocation
            $locations = array_map('trim', explode(',', $locationUuids));
            $serverPayload['deployment'] = [
                'location_uuids' => $locations,
                'allow_overallocation' => false,
            ];

            $server = $api->deployServer($serverPayload);
        }

        try {
            $command = 'UpdateClientProduct';
            $values = [
                'serviceid' => $params['serviceid'],
                'customfields' => base64_encode(serialize([
                    'Server UUID' => $server['uuid'],
                    'Server ID' => $server['uuid_short'] ?? '',
                ])),
            ];
            localAPI($command, $values);
        } catch (\Exception $e) {
            // Non-fatal: server was created, custom field update failed
            logModuleCall('calagopus', 'StoreUUID', $values, $e->getMessage());
        }

        return 'success';
    } catch (\Exception $e) {
        logModuleCall('calagopus', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function calagopus_SuspendAccount(array $params): string
{
    try {
        $api = calagopus_API($params);
        $serverUuid = calagopus_GetServerUuid($api, $params);

        if (!$serverUuid) {
            return 'Server not found.';
        }

        $api->suspendServer($serverUuid);
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('calagopus', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function calagopus_UnsuspendAccount(array $params): string
{
    try {
        $api = calagopus_API($params);
        $serverUuid = calagopus_GetServerUuid($api, $params);

        if (!$serverUuid) {
            return 'Server not found.';
        }

        $api->unsuspendServer($serverUuid);
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('calagopus', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function calagopus_TerminateAccount(array $params): string
{
    try {
        $api = calagopus_API($params);
        $serverUuid = calagopus_GetServerUuid($api, $params);

        if (!$serverUuid) {
            return 'Server not found.';
        }

        $api->deleteServer($serverUuid, false, true);
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('calagopus', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function calagopus_ChangePackage(array $params): string
{
    try {
        $api = calagopus_API($params);
        $serverUuid = calagopus_GetServerUuid($api, $params);

        if (!$serverUuid) {
            return 'Server not found.';
        }

        $ioWeightRaw = calagopus_Cfg($params, 10);
        $ioWeight = $ioWeightRaw !== '' ? (int) $ioWeightRaw : null;

        $featureLimits = [
            'allocations' => (int) calagopus_Cfg($params, 11, '1'),
            'databases'   => (int) calagopus_Cfg($params, 12, '0'),
            'backups'     => (int) calagopus_Cfg($params, 13, '0'),
            'schedules'   => (int) calagopus_Cfg($params, 14, '0'),
        ];

        $customLimits = calagopus_ParseCustomFeatureLimits(calagopus_Cfg($params, 15));
        $featureLimits = array_merge($featureLimits, $customLimits);

        $updateData = [
            'limits' => [
                'cpu'             => (int) calagopus_Cfg($params, 8, '100'),
                'memory'          => (int) calagopus_Cfg($params, 5, '1024'),
                'memory_overhead' => (int) calagopus_Cfg($params, 9, '0'),
                'swap'            => (int) calagopus_Cfg($params, 6, '0'),
                'disk'            => (int) calagopus_Cfg($params, 7, '10240'),
            ],
            'feature_limits' => $featureLimits,
            'hugepages_passthrough_enabled' => calagopus_Cfg($params, 23) === 'on',
            'kvm_passthrough_enabled' => calagopus_Cfg($params, 24) === 'on',
        ];

        $pinnedCpusRaw = calagopus_Cfg($params, 25);
        if ($pinnedCpusRaw !== '') {
            $updateData['pinned_cpus'] = array_map('intval', array_filter(array_map('trim', explode(',', $pinnedCpusRaw)), 'is_numeric'));
        }

        if ($ioWeight !== null) {
            $updateData['limits']['io_weight'] = $ioWeight;
        }

        $dockerImage = calagopus_Cfg($params, 16);
        if ($dockerImage) {
            $updateData['image'] = $dockerImage;
        }

        $api->updateServer($serverUuid, $updateData);

        $variables = calagopus_ParseVariables(calagopus_Cfg($params, 19));
        if (!empty($variables)) {
            $api->updateServerVariables($serverUuid, $variables);
        }

        return 'success';
    } catch (\Exception $e) {
        logModuleCall('calagopus', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function calagopus_ClientArea(array $params): string
{
    try {
        $api = calagopus_API($params);
        $serverUuid = calagopus_GetServerUuid($api, $params);

        if (!$serverUuid) {
            return '<p>Server information not available.</p>';
        }

        $server = $api->getServer($serverUuid);
        $panelUrl = $api->getServerUrl($serverUuid);

        $serverName = htmlspecialchars($server['name'] ?? 'N/A');
        $status = htmlspecialchars($server['status'] ?? ($server['is_suspended'] ? 'Suspended' : 'Active'));
        $ip = 'N/A';
        $port = '';

        if (!empty($server['allocation'])) {
            $ipAlias = $server['allocation']['ip_alias'] ?? $server['allocation']['ip'] ?? '';
            $port = $server['allocation']['port'] ?? '';
            $ip = htmlspecialchars($ipAlias . ':' . $port);
        }

        $memoryMb = $server['limits']['memory'] ?? 0;
        $diskMb = $server['limits']['disk'] ?? 0;
        $cpuPct = $server['limits']['cpu'] ?? 0;

        return '
        <div class="calagopus-client-area" style="font-family: sans-serif;">
            <h3 style="margin-bottom: 15px;">Calagopus Server</h3>
            <table class="table table-bordered" style="max-width: 500px;">
                <tr><td><strong>Server Name</strong></td><td>' . $serverName . '</td></tr>
                <tr><td><strong>Status</strong></td><td>' . $status . '</td></tr>
                <tr><td><strong>Address</strong></td><td>' . $ip . '</td></tr>
                <tr><td><strong>Memory</strong></td><td>' . $memoryMb . ' MB</td></tr>
                <tr><td><strong>Disk</strong></td><td>' . $diskMb . ' MB</td></tr>
                <tr><td><strong>CPU</strong></td><td>' . $cpuPct . '%</td></tr>
            </table>
            <a href="' . htmlspecialchars($panelUrl) . '" target="_blank"
               class="btn btn-primary"
               style="display: inline-block; margin-top: 10px; padding: 8px 20px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px;">
                Go to Server Panel &rarr;
            </a>
        </div>';
    } catch (\Exception $e) {
        return '<p>Error loading server info: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

function calagopus_AdminCustomButtonArray(): array
{
    return [];
}

function calagopus_AdminServicesTabFields(array $params): array
{
    try {
        $api = calagopus_API($params);
        $serverUuid = calagopus_GetServerUuid($api, $params);

        if (!$serverUuid) {
            return ['Server UUID' => 'Not found'];
        }

        $server = $api->getServer($serverUuid);
        $panelUrl = $api->getServerUrl($serverUuid);

        return [
            'Server UUID' => $server['uuid'],
            'Server Name' => $server['name'],
            'Node' => $server['node']['name'] ?? 'N/A',
            'Owner' => ($server['owner']['name_first'] ?? '') . ' ' . ($server['owner']['name_last'] ?? ''),
            'Suspended' => $server['is_suspended'] ? 'Yes' : 'No',
            'Panel Link' => '<a href="' . htmlspecialchars($panelUrl) . '" target="_blank">Open in Panel</a>',
        ];
    } catch (\Exception $e) {
        return ['Error' => $e->getMessage()];
    }
}

/**
 * Try to find the server UUID from:
 * 1. Custom fields on the service
 * 2. External ID lookup on the panel
 */
function calagopus_GetServerUuid(CalagopusAPI $api, array $params): ?string
{
    if (!empty($params['customfields']['Server UUID'])) {
        return $params['customfields']['Server UUID'];
    }

    // Fallback: look up by external ID
    $externalId = (string) $params['serviceid'];
    $server = $api->getServerByExternalId($externalId);

    return $server['uuid'] ?? null;
}
