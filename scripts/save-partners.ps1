$latest = Get-ChildItem 'C:\Users\user\.cursor\browser-logs\cdp-response-Runtime.evaluate-*.json' |
    Where-Object { (Get-Content $_.FullName -Raw) -match '"fedex"' } |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1

if (-not $latest) {
    throw 'No CDP log file found for partner assets.'
}

$payload = (Get-Content $latest.FullName -Raw | ConvertFrom-Json).result.value
$data = $payload | ConvertFrom-Json
$dest = Join-Path $PSScriptRoot '..\asset\home'

$map = @{
    'partner-fedex.webp' = 'fedex'
    'partner-usps.webp'  = 'usps'
    'partner-ups.webp'   = 'ups'
    'partner-amazon.webp'= 'amazon'
    'partner-msc.webp'   = 'msc'
}

foreach ($entry in $map.GetEnumerator()) {
    $b64 = $data.($entry.Value)
    if (-not $b64) {
        Write-Warning "Missing $($entry.Value)"
        continue
    }
    $out = Join-Path $dest $entry.Key
    [IO.File]::WriteAllBytes($out, [Convert]::FromBase64String($b64))
    Write-Host "Saved $out"
}

Get-ChildItem (Join-Path $dest 'partner-*') | Select-Object Name, Length | Format-Table
