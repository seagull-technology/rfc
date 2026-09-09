param(
    [string] $ScriptPath = (Join-Path $PSScriptRoot "..\..\deployment\windows\Deploy-RfcRelease.ps1")
)

# Real HTTP transport tests, bound only to ephemeral 127.0.0.1 ports and using a
# fake maintenance secret. No application, IIS, public endpoint, or SMS is used.
# Local execution uses PowerShell 7; Windows PowerShell 5.1 integration remains
# a separate server check even though the helper uses the compatible .NET API.
$ErrorActionPreference = "Stop"
$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile((Resolve-Path $ScriptPath).Path, [ref] $tokens, [ref] $parseErrors)
if ($parseErrors.Count -gt 0) { throw (($parseErrors | ForEach-Object { $_.Message }) -join [Environment]::NewLine) }
foreach ($name in @("New-MaintenanceSmokeCookie", "Invoke-RfcMaintenanceSmokeRequest")) {
    $definition = $ast.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $true) |
        Where-Object { $_.Name -eq $name } | Select-Object -First 1
    if (-not $definition) { throw "Missing smoke helper: $name" }
    . ([ScriptBlock]::Create($definition.Extent.Text))
}
function Assert-True {
    param([bool] $Condition, [string] $Message)
    if (-not $Condition) { throw $Message }
}
function Assert-Throws {
    param([ScriptBlock] $Action, [string] $Message)
    $threw = $false
    try { & $Action | Out-Null } catch { $threw = $true }
    Assert-True $threw $Message
}

Add-Type -TypeDefinition @"
using System;
using System.Collections.Generic;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
public sealed class RfcSmokeLoopbackFixture : IDisposable {
    private readonly TcpListener listener;
    private readonly Task worker;
    private readonly int status;
    private readonly string location;
    private volatile bool disposed;
    public readonly ManualResetEventSlim Received = new ManualResetEventSlim(false);
    public readonly Dictionary<string, string> Headers = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
    public string RequestLine { get; private set; }
    public Exception ServerError { get; private set; }
    public string Uri { get; private set; }
    public RfcSmokeLoopbackFixture(int statusCode, string redirectLocation) {
        status = statusCode;
        location = redirectLocation;
        listener = new TcpListener(IPAddress.Loopback, 0);
        listener.Start();
        Uri = "http://127.0.0.1:" + ((IPEndPoint)listener.LocalEndpoint).Port + "/ar/sign-in";
        worker = Task.Run((Action)ServeOneRequest);
    }
    private void ServeOneRequest() {
        try {
            using (TcpClient client = listener.AcceptTcpClient()) {
                client.ReceiveTimeout = 5000;
                client.SendTimeout = 5000;
                using (NetworkStream stream = client.GetStream()) {
                    using (StreamReader reader = new StreamReader(stream, Encoding.ASCII, false, 4096, true)) {
                        RequestLine = reader.ReadLine();
                        for (int count = 0; count < 100; count++) {
                            string line = reader.ReadLine();
                            if (String.IsNullOrEmpty(line)) break;
                            int colon = line.IndexOf(':');
                            if (colon > 0) Headers[line.Substring(0, colon)] = line.Substring(colon + 1).Trim();
                        }
                    }
                    Received.Set();
                    string body = status == 503 ? "PRIVATE_FIXTURE_BODY_MUST_NOT_APPEAR" : "local smoke fixture";
                    string reason = status == 302 ? "Found" : (status == 503 ? "Service Unavailable" : "OK");
                    string response = "HTTP/1.1 " + status + " " + reason + "\r\nConnection: close\r\nContent-Type: text/plain\r\nContent-Length: " + Encoding.UTF8.GetByteCount(body) + "\r\n";
                    if (!String.IsNullOrEmpty(location)) response += "Location: " + location + "\r\n";
                    byte[] bytes = Encoding.UTF8.GetBytes(response + "\r\n" + body);
                    stream.Write(bytes, 0, bytes.Length);
                    stream.Flush();
                }
            }
        }
        catch (Exception error) { if (!disposed) ServerError = error; }
    }
    public void Dispose() {
        disposed = true;
        listener.Stop();
        worker.Wait(6000);
        Received.Dispose();
    }
}
"@

$maintenanceSecret = "fake-local-transport-maintenance-secret"
$checks = 0
$server = [RfcSmokeLoopbackFixture]::new(200, $null)
try {
    $result = Invoke-RfcMaintenanceSmokeRequest -SmokeHost "filmjordan.jo" -RequestUri ([Uri] $server.Uri)
    Assert-True ($result.StatusCode -eq 200) "The helper must report the listener's successful response."
    Assert-True ($server.Received.Wait(1000)) "The local listener did not receive the request."
    Assert-True ($null -eq $server.ServerError) "The local listener failed."
    $receivedHost = [Uri] ("http://" + $server.Headers["Host"])
    Assert-True ($receivedHost.Host -ceq "filmjordan.jo" -and $receivedHost.Port -eq 80) "The custom IIS Host authority must arrive instead of the loopback endpoint."
    Assert-True ($server.RequestLine -ceq "GET /ar/sign-in HTTP/1.1") "The smoke target must not put the bypass secret in the URL."
    $cookieHeader = $server.Headers["Cookie"]
    Assert-True ($cookieHeader -cmatch '^laravel_maintenance=([A-Za-z0-9%]+)$') "The signed cookie must arrive despite the custom Host header."
    $payload = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String([Uri]::UnescapeDataString($Matches[1]))) | ConvertFrom-Json
    $now = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    Assert-True ($payload.expires_at -gt $now -and $payload.expires_at -le ($now + 600)) "The transported bypass cookie must have a bounded future expiry."
    $hmac = New-Object Security.Cryptography.HMACSHA256
    try {
        $hmac.Key = [Text.Encoding]::UTF8.GetBytes($maintenanceSecret)
        $digest = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes(([long] $payload.expires_at).ToString([Globalization.CultureInfo]::InvariantCulture)))
        $expectedMac = ([BitConverter]::ToString($digest)).Replace("-", "").ToLowerInvariant()
        Assert-True ($payload.mac -ceq $expectedMac) "The received cookie HMAC must match Laravel's maintenance-secret signing rule."
    }
    finally { $hmac.Dispose() }
    $checks++
}
finally { $server.Dispose() }

$redirectTarget = [RfcSmokeLoopbackFixture]::new(200, $null)
$redirectSource = [RfcSmokeLoopbackFixture]::new(302, $redirectTarget.Uri)
try {
    $result = Invoke-RfcMaintenanceSmokeRequest -SmokeHost "filmjordan.jo" -RequestUri ([Uri] $redirectSource.Uri)
    Assert-True ($result.StatusCode -eq 302) "The helper must return the redirect status for the caller to reject."
    Assert-True ($redirectSource.Received.Wait(1000)) "The redirect source did not receive the request."
    Assert-True (-not $redirectTarget.Received.Wait(250)) "A redirect must never receive another request or the maintenance bypass cookie."
    $checks++
}
finally { $redirectSource.Dispose(); $redirectTarget.Dispose() }

$unavailable = [RfcSmokeLoopbackFixture]::new(503, $null)
try {
    $failure = $null
    try { Invoke-RfcMaintenanceSmokeRequest -SmokeHost "filmjordan.jo" -RequestUri ([Uri] $unavailable.Uri) | Out-Null }
    catch { $failure = $_ }
    Assert-True ($null -ne $failure) "Maintenance HTTP 503 must fail the smoke request."
    Assert-True ($failure.Exception.ToString() -notmatch 'PRIVATE_FIXTURE_BODY_MUST_NOT_APPEAR|fake-local-transport-maintenance-secret|laravel_maintenance=') "HTTP failures must not dump response bodies or bypass credentials."
    $checks++
}
finally { $unavailable.Dispose() }

foreach ($invalidUri in @(
    "https://127.0.0.1/ar/sign-in", "http://localhost/ar/sign-in", "http://[::1]/ar/sign-in",
    "http://192.0.2.1/ar/sign-in", "http://user@127.0.0.1/ar/sign-in", "http://127.0.0.1/ar/sign-in#fragment",
    "/ar/sign-in"
)) {
    Assert-Throws { Invoke-RfcMaintenanceSmokeRequest -SmokeHost "filmjordan.jo" -RequestUri ([Uri] $invalidUri) } "Invalid smoke URI must be rejected before transport: $invalidUri"
}
$checks++
Write-Host "$checks maintenance smoke transport scenarios passed using fake cookies and loopback listeners only."
