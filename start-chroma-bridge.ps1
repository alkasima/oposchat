<#
.SYNOPSIS
    Starts the Chroma Cloud Bridge service if it is not already running.
    Can be scheduled in Windows Task Scheduler to run every minute (it will exit immediately if already running).

.DESCRIPTION
    Checks if a process is listening on port 8001. If not, it activates the Python virtual environment
    and starts the Uvicorn server for the chroma-bridge.

.EXAMPLE
    ./start-chroma-bridge.ps1
#>

$port = 8001
$scriptPath = $PSScriptRoot
$bridgePath = Join-Path $scriptPath "chroma-bridge"
$venvPython = Join-Path $bridgePath "venv\Scripts\python.exe"
$appFile = "app.py"

# Function to check if port is listening
function Test-PortOpen {
    param ($p)
    $netstat = netstat -an | Select-String ":$p" | Select-String "LISTENING"
    return $null -ne $netstat
}

Write-Host "Checking status of Chroma Bridge on port $port..."

if (Test-PortOpen $port) {
    Write-Host "✅ Chroma Bridge is already running on port $port." -ForegroundColor Green
    exit 0
}

Write-Host "⚠️ Port $port is not in use. Starting Chroma Bridge..." -ForegroundColor Yellow

if (-not (Test-Path $venvPython)) {
    Write-Error "❌ Virtual environment python not found at $venvPython"
    exit 1
}

# Start the process in the background
$process = Start-Process -FilePath $venvPython -ArgumentList "$appFile" -WorkingDirectory $bridgePath -PassThru -NoNewWindow
# Note: In a production server environment where you want this to persist after logout, 
# you might use a service manager like NSSM. For now, this keeps it running in this session or via scheduler.

Start-Sleep -Seconds 3

if (Test-PortOpen $port) {
    Write-Host "✅ Chroma Bridge started successfully (PID: $($process.Id))." -ForegroundColor Green
} else {
    Write-Error "❌ Failed to start Chroma Bridge. Please check logs."
}
