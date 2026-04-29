## GitHub Copilot Chat

- Extension: 0.46.2026042405 (prod)
- VS Code: 1.118.0-insider (f2b51f3f64f0a781a7633c2243cfdde589030e34)
- OS: win32 10.0.26200 x64
- GitHub Account: Stevenjoe08

## Network

User Settings:
```json
  "http.systemCertificatesNode": true,
  "github.copilot.advanced.debug.useElectronFetcher": true,
  "github.copilot.advanced.debug.useNodeFetcher": false,
  "github.copilot.advanced.debug.useNodeFetchFetcher": true
```

Connecting to https://api.github.com:
- DNS ipv4 Lookup: Error (1 ms): getaddrinfo ENOTFOUND api.github.com
- DNS ipv6 Lookup: Error (1 ms): getaddrinfo ENOTFOUND api.github.com
- Proxy URL: None (2 ms)
- Electron fetch (configured): Error (5 ms): Error: net::ERR_NAME_NOT_RESOLVED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
	at SimpleURLLoaderWrapper.callbackTrampoline (node:internal/async_hooks:130:17)
  {"is_request_error":true,"network_process_crashed":false}
- Node.js https: Error (44 ms): Error: getaddrinfo ENOTFOUND api.github.com
	at GetAddrInfoReqWrap.onlookupall [as oncomplete] (node:dns:122:26)
	at GetAddrInfoReqWrap.callbackTrampoline (node:internal/async_hooks:130:17)
- Node.js fetch: Error (31 ms): TypeError: fetch failed
	at node:internal/deps/undici/undici:14902:13
	at process.processTicksAndRejections (node:internal/process/task_queues:103:5)
	at async t._fetch (c:\Users\patri\AppData\Local\Programs\Microsoft VS Code Insiders\f2b51f3f64\resources\app\extensions\copilot\dist\extension.js:5319:5229)
	at async t.fetch (c:\Users\patri\AppData\Local\Programs\Microsoft VS Code Insiders\f2b51f3f64\resources\app\extensions\copilot\dist\extension.js:5319:4541)
	at async u (c:\Users\patri\AppData\Local\Programs\Microsoft VS Code Insiders\f2b51f3f64\resources\app\extensions\copilot\dist\extension.js:5351:186)
	at async wg._executeContributedCommand (file:///c:/Users/patri/AppData/Local/Programs/Microsoft%20VS%20Code%20Insiders/f2b51f3f64/resources/app/out/vs/workbench/api/node/extensionHostProcess.js:503:48675)
  Error: getaddrinfo ENOTFOUND api.github.com
  	at GetAddrInfoReqWrap.onlookupall [as oncomplete] (node:dns:122:26)
  	at GetAddrInfoReqWrap.callbackTrampoline (node:internal/async_hooks:130:17)

Connecting to https://api.githubcopilot.com/_ping:
- DNS ipv4 Lookup: Error (1 ms): getaddrinfo ENOTFOUND api.githubcopilot.com
- DNS ipv6 Lookup: Error (14 ms): getaddrinfo ENOTFOUND api.githubcopilot.com
- Proxy URL: None (56 ms)
- Electron fetch (configured): Error (3 ms): Error: net::ERR_NAME_NOT_RESOLVED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
	at SimpleURLLoaderWrapper.callbackTrampoline (node:internal/async_hooks:130:17)
  {"is_request_error":true,"network_process_crashed":false}
- Node.js https: Error (23 ms): Error: getaddrinfo ENOTFOUND api.githubcopilot.com
	at GetAddrInfoReqWrap.onlookupall [as oncomplete] (node:dns:122:26)
	at GetAddrInfoReqWrap.callbackTrampoline (node:internal/async_hooks:130:17)
- Node.js fetch: Error (32 ms): TypeError: fetch failed
	at node:internal/deps/undici/undici:14902:13
	at process.processTicksAndRejections (node:internal/process/task_queues:103:5)
	at async t._fetch (c:\Users\patri\AppData\Local\Programs\Microsoft VS Code Insiders\f2b51f3f64\resources\app\extensions\copilot\dist\extension.js:5319:5229)
	at async t.fetch (c:\Users\patri\AppData\Local\Programs\Microsoft VS Code Insiders\f2b51f3f64\resources\app\extensions\copilot\dist\extension.js:5319:4541)
	at async u (c:\Users\patri\AppData\Local\Programs\Microsoft VS Code Insiders\f2b51f3f64\resources\app\extensions\copilot\dist\extension.js:5351:186)
	at async wg._executeContributedCommand (file:///c:/Users/patri/AppData/Local/Programs/Microsoft%20VS%20Code%20Insiders/f2b51f3f64/resources/app/out/vs/workbench/api/node/extensionHostProcess.js:503:48675)
  Error: getaddrinfo ENOTFOUND api.githubcopilot.com
  	at GetAddrInfoReqWrap.onlookupall [as oncomplete] (node:dns:122:26)
  	at GetAddrInfoReqWrap.callbackTrampoline (node:internal/async_hooks:130:17)

Connecting to https://copilot-proxy.githubusercontent.com/_ping:
- DNS ipv4 Lookup: Error (1 ms): getaddrinfo ENOTFOUND copilot-proxy.githubusercontent.com
- DNS ipv6 Lookup: Error (1 ms): getaddrinfo ENOTFOUND copilot-proxy.githubusercontent.com
- Proxy URL: None (14 ms)
- Electron fetch (configured): Error (7 ms): Error: net::ERR_NAME_NOT_RESOLVED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
	at SimpleURLLoaderWrapper.callbackTrampoline (node:internal/async_hooks:130:17)
  {"is_request_error":true,"network_process_crashed":false}
- Node.js https: Error (22 ms): Error: getaddrinfo ENOTFOUND copilot-proxy.githubusercontent.com
	at GetAddrInfoReqWrap.onlookupall [as oncomplete] (node:dns:122:26)
	at GetAddrInfoReqWrap.callbackTrampoline (node:internal/async_hooks:130:17)
- Node.js fetch: Error (31 ms): TypeError: fetch failed
	at node:internal/deps/undici/undici:14902:13
	at process.processTicksAndRejections (node:internal/process/task_queues:103:5)
	at async t._fetch (c:\Users\patri\AppData\Local\Programs\Microsoft VS Code Insiders\f2b51f3f64\resources\app\extensions\copilot\dist\extension.js:5319:5229)
	at async t.fetch (c:\Users\patri\AppData\Local\Programs\Microsoft VS Code Insiders\f2b51f3f64\resources\app\extensions\copilot\dist\extension.js:5319:4541)
	at async u (c:\Users\patri\AppData\Local\Programs\Microsoft VS Code Insiders\f2b51f3f64\resources\app\extensions\copilot\dist\extension.js:5351:186)
	at async wg._executeContributedCommand (file:///c:/Users/patri/AppData/Local/Programs/Microsoft%20VS%20Code%20Insiders/f2b51f3f64/resources/app/out/vs/workbench/api/node/extensionHostProcess.js:503:48675)
  Error: getaddrinfo ENOTFOUND copilot-proxy.githubusercontent.com
  	at GetAddrInfoReqWrap.onlookupall [as oncomplete] (node:dns:122:26)
  	at GetAddrInfoReqWrap.callbackTrampoline (node:internal/async_hooks:130:17)

Connecting to https://mobile.events.data.microsoft.com: Error (5 ms): Error: net::ERR_NAME_NOT_RESOLVED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
	at SimpleURLLoaderWrapper.callbackTrampoline (node:internal/async_hooks:130:17)
  {"is_request_error":true,"network_process_crashed":false}
Connecting to https://dc.services.visualstudio.com: Error (5 ms): Error: net::ERR_NAME_NOT_RESOLVED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
	at SimpleURLLoaderWrapper.callbackTrampoline (node:internal/async_hooks:130:17)
  {"is_request_error":true,"network_process_crashed":false}
Connecting to https://copilot-telemetry.githubusercontent.com/_ping: Error (62 ms): Error: getaddrinfo ENOTFOUND copilot-telemetry.githubusercontent.com
	at GetAddrInfoReqWrap.onlookupall [as oncomplete] (node:dns:122:26)
	at GetAddrInfoReqWrap.callbackTrampoline (node:internal/async_hooks:130:17)
Connecting to https://copilot-telemetry.githubusercontent.com/_ping: Error (19 ms): Error: getaddrinfo ENOTFOUND copilot-telemetry.githubusercontent.com
	at GetAddrInfoReqWrap.onlookupall [as oncomplete] (node:dns:122:26)
	at GetAddrInfoReqWrap.callbackTrampoline (node:internal/async_hooks:130:17)
Connecting to https://default.exp-tas.com: Error (22 ms): Error: getaddrinfo ENOTFOUND default.exp-tas.com
	at GetAddrInfoReqWrap.onlookupall [as oncomplete] (node:dns:122:26)
	at GetAddrInfoReqWrap.callbackTrampoline (node:internal/async_hooks:130:17)

Number of system certificates: 90

## Documentation

In corporate networks: [Troubleshooting firewall settings for GitHub Copilot](https://docs.github.com/en/copilot/troubleshooting-github-copilot/troubleshooting-firewall-settings-for-github-copilot).