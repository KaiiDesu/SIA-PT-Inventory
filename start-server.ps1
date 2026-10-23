# Animated launcher: checks PHP, MySQL and the port, then runs the Inventory system
# on PHP's built-in web server (no Apache needed). start-server.bat calls this.
param(
    [int]$Port = 8000,
    [int]$DbPort = 3306,
    [string]$Php = 'C:\xampp\php\php.exe'
)

[Console]::OutputEncoding = [Text.Encoding]::UTF8
$root  = $PSScriptRoot
$url   = "http://localhost:$Port/"
# Built from code points so the file stays plain ASCII for Windows PowerShell 5.1.
$tick  = [char]0x2713
$cross = [char]0x2717
$full  = [char]0x2588
$empty = [char]0x2591

function Show-Banner {
    $art = @'
   ____                                _____ ____  ____
  / ___|__ _ _ __ ___  _ __  _   _ ___| ____|  _ \|  _ \
 | |   / _` | '_ ` _ \| '_ \| | | / __|  _| | |_) | |_) |
 | |__| (_| | | | | | | |_) | |_| \__ \ |___|  _ <|  __/
  \____\__,_|_| |_| |_| .__/ \__,_|___/_____|_| \_\_|
                      |_|
'@
    Clear-Host
    Write-Host
    foreach ($line in $art -split "`r?`n") {
        Write-Host $line -ForegroundColor Green
        Start-Sleep -Milliseconds 70
    }
    Write-Host
    foreach ($ch in '  INVENTORY MANAGEMENT  .  Mini-ERP System 3'.ToCharArray()) {
        Write-Host $ch -NoNewline -ForegroundColor DarkGreen
        Start-Sleep -Milliseconds 12
    }
    Write-Host "`n"
}

# Spins beside $Label for a moment, then runs $Test and prints a tick or cross.
function Invoke-Step([string]$Label, [scriptblock]$Test) {
    $frames = '|', '/', '-', '\'
    for ($i = 0; $i -lt 10; $i++) {
        Write-Host "`r  $($frames[$i % 4])  $Label" -NoNewline -ForegroundColor Yellow
        Start-Sleep -Milliseconds 60
    }
    $ok = & $Test
    if ($ok) { Write-Host "`r  $tick  $Label" -ForegroundColor Green }
    else     { Write-Host "`r  $cross  $Label" -ForegroundColor Red }
    return [bool]$ok
}

function Test-Port([int]$P) {
    $client = New-Object Net.Sockets.TcpClient
    try { return $client.ConnectAsync('127.0.0.1', $P).Wait(400) -and $client.Connected }
    catch { return $false }
    finally { $client.Dispose() }
}

function Show-Progress([string]$Label) {
    $width = 30
    for ($i = 0; $i -le $width; $i++) {
        $bar = ([string]$full * $i) + ([string]$empty * ($width - $i))
        $pct = [int](100 * $i / $width)
        Write-Host "`r  $bar  $pct%  $Label" -NoNewline -ForegroundColor Green
        Start-Sleep -Milliseconds 25
    }
    Write-Host "`n"
}

Show-Banner

if (-not (Invoke-Step "PHP found at $Php" { Test-Path $Php })) {
    Write-Host "`n  Edit the `$Php line at the top of start-server.ps1 to point to php.exe." -ForegroundColor Red
    exit 1
}

if (-not (Invoke-Step "MySQL is reachable on port $DbPort" { Test-Port $DbPort })) {
    Write-Host '     Start MySQL (services.msc or the XAMPP Control Panel), or pages will show a database error.' -ForegroundColor DarkYellow
}

if (-not (Invoke-Step "Port $Port is free" { -not (Test-Port $Port) })) {
    Write-Host "     Something is already using port $Port - probably the server is already running." -ForegroundColor DarkYellow
    Write-Host "     Opening $url ..." -ForegroundColor DarkYellow
    Start-Process $url
    exit 0
}

Write-Host
Show-Progress 'Starting server'

Write-Host '  +------------------------------------------------+' -ForegroundColor DarkGreen
Write-Host "  |  Running at  $($url.PadRight(34))|" -ForegroundColor Green
Write-Host '  |  Press Ctrl+C to stop                          |' -ForegroundColor DarkGreen
Write-Host '  +------------------------------------------------+' -ForegroundColor DarkGreen
Write-Host

Start-Process $url
& $Php -S "localhost:$Port" -t $root
