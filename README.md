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
