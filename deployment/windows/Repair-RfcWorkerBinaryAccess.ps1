# Targeted repair for the known staging installer ACL defect. Run elevated.
# The QueueWorker parent ACL was inspected: SYSTEM/Administrators FullControl,
# NT SERVICE\RFCQueueWorker ReadAndExecute. Only the executable's inheritance
# is changed. No service is started and no queued jobs are processed.
$ErrorActionPreference = "Stop"
$rfcBinary = 'C:\Program Files\RFC\QueueWorker\nssm.exe'
$rfcService = Get-CimInstance Win32_Service -Filter "Name='RFCQueueWorker'"
if (-not $rfcService -or $rfcService.State -ne 'Stopped' -or
    $rfcService.StartMode -ne 'Manual' -or
    $rfcService.StartName -ine 'NT SERVICE\RFCQueueWorker' -or
    ([string] $rfcService.PathName).Trim() -ine ('"' + $rfcBinary + '"')) {
    throw 'Worker configuration changed. Stop and share this error.'
}

$global:LASTEXITCODE = $null
& icacls.exe $rfcBinary /inheritance:e
if ($null -eq $LASTEXITCODE -or $LASTEXITCODE -ne 0) { throw 'Permission repair failed. Stop here.' }

if ((Get-FileHash -LiteralPath $rfcBinary -Algorithm SHA256).Hash -ne
    'eee9c44c29c2be011f1f1e43bb8c3fca888cb81053022ec5a0060035de16d848') {
    throw 'NSSM checksum mismatch. Stop here.'
}
$global:LASTEXITCODE = $null
& $rfcBinary version
if ($null -eq $LASTEXITCODE -or $LASTEXITCODE -ne 0) { throw 'NSSM executable check failed.' }
$global:LASTEXITCODE = $null
& icacls.exe $rfcBinary
if ($null -eq $LASTEXITCODE -or $LASTEXITCODE -ne 0) { throw 'Cannot inspect the repaired permissions.' }

foreach ($rfcLog in @(
    'C:\ProgramData\RFC\QueueWorker\Logs'
    'C:\ProgramData\RFC\QueueWorker\Logs\worker-out.log'
    'C:\ProgramData\RFC\QueueWorker\Logs\worker-error.log'
)) {
    if (Test-Path -LiteralPath $rfcLog) {
        $global:LASTEXITCODE = $null
        & icacls.exe $rfcLog
        if ($null -eq $LASTEXITCODE -or $LASTEXITCODE -ne 0) { throw "Cannot inspect log permissions: $rfcLog" }
    }
    else { Write-Host "Not created: $rfcLog" }
}
$rfcService = Get-CimInstance Win32_Service -Filter "Name='RFCQueueWorker'"
if (-not $rfcService -or $rfcService.State -ne 'Stopped' -or $rfcService.StartMode -ne 'Manual') {
    throw 'Worker state changed unexpectedly. Stop and share the output.'
}
$rfcService | Select-Object Name, State, StartMode
