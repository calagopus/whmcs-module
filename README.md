![Calagopus Logo](https://calagopus.com/fulllogo.svg)

# Calagopus WHMCS Module

<https://calagopus.com>

## Installation

1. Upload the `modules/servers/calagopus/` directory to your WHMCS installation at:

   ```sh
   /path/to/whmcs/modules/servers/calagopus/
   ```

2. In WHMCS Admin, go to **System Settings → Servers → Add New Server**:
   - **Module**: Select "Calagopus"
   - **Hostname**: Your panel domain (e.g. `panel.example.com`)
   - **Password**: Your Calagopus admin API key
   - **Secure**: Check if using HTTPS
   - Click "Test Connection" to verify

3. Create a **Server Group** and assign your Calagopus server to it.

4. Create a **Product** and set the module to "Calagopus", then configure the product options.

## Overwriting Values Through Configurable Options

Every module setting can be overwritten per service through WHMCS
**Configurable Options** or **Custom Fields**, just like the official
Pterodactyl module. Resolution priority:

1. Configurable Option matching the setting's friendly name (e.g. `Memory (MB)`)
2. Configurable Option matching the setting's key (e.g. `memory`)
3. Custom Field matching the friendly name
4. Custom Field matching the key
5. The product's module setting
6. Module default

A configurable option named `Memory (MB)|Memory` works too - WHMCS only
passes the part before the `|` to the module.

### Overridable settings

| Key | Friendly Name |
| --- | --- |
| `nest_uuid` | Nest UUID |
| `egg_uuid` | Egg UUID |
| `node_uuid` | Node UUID (optional) |
| `location_uuids` | Location UUIDs (deploy mode) |
| `memory` | Memory (MB) |
| `swap` | Swap (MB) |
| `disk` | Disk (MB) |
| `cpu` | CPU Limit (%) |
| `memory_overhead` | Memory Overhead (MB) |
| `io_weight` | IO Weight (10-1000, blank=default) |
| `allocations_limit` | Allocation Limit |
| `database_limit` | Database Limit |
| `backup_limit` | Backup Limit |
| `schedule_limit` | Schedule Limit |
| `custom_feature_limits` | Custom Feature Limits |
| `docker_image` | Docker Image (optional) |
| `startup_command` | Startup Command (optional) |
| `server_name_prefix` | Server Name Prefix |
| `variables` | Egg Variables |
| `skip_installer` | Skip Installer |
| `start_on_completion` | Start on Completion |
| `backup_configuration_uuid` | Backup Configuration UUID (optional) |
| `hugepages_passthrough` | Hugepages Passthrough |
| `kvm_passthrough` | KVM Passthrough |

For yes/no settings, override values of `on`, `1`, `yes` or `true` enable the
setting; anything else disables it.

### Extra keys (no product-setting fallback)

These have no field on the product page but are honored when supplied as a
configurable option or custom field:

| Key | Friendly Name | Purpose |
| --- | --- | --- |
| `server_name` | Server Name | Full server name (replaces prefix + service id) |
| `pinned_cpus` | - | Comma-separated CPU core ids, e.g. `0,1,2` |

### Egg variables

Each egg variable can be overwritten by a configurable option or custom field
named after the variable's **display name** or its **environment variable
name**. Example: for a variable `Minecraft Version` / `MC_VERSION`, either
`Minecraft Version` or `MC_VERSION` works as the option name.

### Custom feature limits

Each key defined in the product's *Custom Feature Limits* field (e.g.
`plugins:5,worlds:3`) can be overwritten individually by a configurable option
or custom field with the same name (e.g. an option named `plugins`). The key
must be present in the product's base definition to be sent to the panel.
