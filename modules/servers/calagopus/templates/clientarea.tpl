<div class="calagopus-server text-left">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div class="mb-2">
            <h3 class="mb-1">{if $server}{$server.name|escape}{else}Server Details{/if}</h3>
            {if $server && $server.egg}
                <span class="text-muted small">{$server.egg|escape}{if $server.nest} &middot; {$server.nest|escape}{/if}</span>
            {/if}
        </div>
        <div class="d-flex align-items-center mb-2">
            {if $server && $server.state}
                <span class="badge badge-{$server.state.color|escape} mr-2">{$server.state.label|escape}</span>
            {/if}
            {if $panelUrl}
                <a class="btn btn-primary btn-sm" href="{$panelUrl|escape}" target="_blank" rel="noopener">Open Panel</a>
            {/if}
        </div>
    </div>

    {if $error}
        <div class="alert alert-warning mb-0" role="alert">{$error|escape}</div>
    {elseif !$server}
        <div class="alert alert-info mb-0" role="alert">Your server is still being set up. Check back shortly.</div>
    {else}
        <dl class="row mb-0">
            <dt class="col-sm-3 text-muted">Address</dt>
            <dd class="col-sm-9">
                {if $server.address}
                    <span class="text-monospace">{$server.address|escape}</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 ml-2" data-calagopus-copy="{$server.address|escape}">Copy</button>
                {else}
                    <span class="text-muted">No address assigned</span>
                {/if}
            </dd>

            {if $server.location}
                <dt class="col-sm-3 text-muted">Location</dt>
                <dd class="col-sm-9">{$server.location|escape}</dd>
            {/if}

            {if $server.uptime}
                <dt class="col-sm-3 text-muted">Uptime</dt>
                <dd class="col-sm-9">{$server.uptime|escape}</dd>
            {/if}

            {foreach $server.resources as $label => $resource}
                <dt class="col-sm-3 text-muted">{$label|escape}</dt>
                <dd class="col-sm-9">
                    {if isset($resource.used)}{$resource.used|escape} / {/if}{$resource.limit|escape}
                    {if isset($resource.percent)}
                        <div class="progress mt-1" style="height: 6px; max-width: 320px;" role="progressbar" aria-label="{$label|escape}" aria-valuenow="{$resource.percent}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar{if $resource.percent >= 90} bg-danger{elseif $resource.percent >= 75} bg-warning{/if}" style="width: {$resource.percent}%"></div>
                        </div>
                    {/if}
                </dd>
            {/foreach}

            <dt class="col-sm-3 text-muted">Includes</dt>
            <dd class="col-sm-9 mb-0">
                {foreach $server.includes as $include}
                    <span class="text-nowrap">{$include.label|escape} <strong>{$include.value|escape}</strong>{if !$include@last}<span class="text-muted"> &middot; </span>{/if}</span>
                {/foreach}
            </dd>
        </dl>

        <script>
            document.querySelectorAll('[data-calagopus-copy]').forEach(function (button) {
                button.addEventListener('click', function () {
                    navigator.clipboard.writeText(button.dataset.calagopusCopy).then(function () {
                        button.textContent = 'Copied';
                        setTimeout(function () {
                            button.textContent = 'Copy';
                        }, 2000);
                    });
                });
            });
        </script>
    {/if}
</div>
