<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

require_once __DIR__ . '/lib/CalagopusAPI.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\Calagopus\CalagopusAPI;

/**
 * Server options are read from the server's Access Hash field, one "key=value" per line,
 * since WHMCS has no custom per-server settings for provisioning modules.
 */
function hook_calagopus_ServerOptionEnabled(string $accessHash, string $key): bool
{
    foreach (explode("\n", $accessHash) as $line) {
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            if (trim($name) === $key) {
                return in_array(strtolower(trim($value)), ['on', '1', 'yes', 'true'], true);
            }
        }
    }

    return false;
}

/**
 * Delete the panel user linked to a WHMCS client, including every server they own, on every
 * Calagopus server that has delete_users_on_delete enabled. WHMCS does not let this hook abort
 * the deletion, so failures are logged and the next server is still processed.
 */
function hook_calagopus_PreDeleteClient(array $vars)
{
    $externalUserId = (string) $vars['userid'];

    foreach (Capsule::table('tblservers')->where('type', 'calagopus')->get() as $server) {
        if (!hook_calagopus_ServerOptionEnabled((string) $server->accesshash, 'delete_users_on_delete')) {
            continue;
        }

        $apiKey = '';

        try {
            $apiKey = localAPI('DecryptPassword', ['password2' => $server->password])['password'] ?? '';

            $url = ($server->secure ? 'https' : 'http') . '://' . $server->hostname;
            if (!empty($server->port)) {
                $url .= ':' . $server->port;
            }

            $api = new CalagopusAPI($url, $apiKey);

            $panelUser = $api->getUserByExternalId($externalUserId);
            if (!$panelUser) {
                continue;
            }

            foreach ($api->getUserOwnedServers($panelUser['uuid']) as $panelServer) {
                try {
                    $api->deleteServer($panelServer['uuid'], true, true);
                } catch (\Exception $e) {
                    if (!str_contains($e->getMessage(), '404')) {
                        throw $e;
                    }
                }
            }

            $api->deleteUser($panelUser['uuid']);
        } catch (\Exception $e) {
            logModuleCall('calagopus', 'PreDeleteClient', ['userid' => $externalUserId, 'serverid' => $server->id, 'hostname' => $server->hostname], $e->getMessage(), $e->getTraceAsString(), [$apiKey]);
        }
    }
}

add_hook('PreDeleteClient', 1, 'hook_calagopus_PreDeleteClient');
