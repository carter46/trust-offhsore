$dest = Join-Path $PSScriptRoot '..\asset\home'

function Save-FromCdpJson {
    param([string]$JsonPath, [string]$OutName, [switch]$NestedJson)
    $raw = Get-Content $JsonPath -Raw | ConvertFrom-Json
    $value = $raw.result.value
    if ($NestedJson) {
        $parsed = $value | ConvertFrom-Json
        $b64 = $parsed.b64
        Write-Host "$OutName size from nested json: $($parsed.size)"
    } else {
        $b64 = $value
    }
    $out = Join-Path $dest $OutName
    [IO.File]::WriteAllBytes($out, [Convert]::FromBase64String($b64))
    Write-Host "Saved $out"
}

Save-FromCdpJson -JsonPath 'C:\Users\user\.cursor\browser-logs\cdp-response-Runtime.evaluate-2026-06-24T18-47-39-223Z.json' -OutName 'logo.webp'
Save-FromCdpJson -JsonPath 'C:\Users\user\.cursor\browser-logs\cdp-response-Runtime.evaluate-2026-06-24T18-48-02-283Z.json' -OutName 'footer-cta-bg.webp' -NestedJson

Get-ChildItem $dest | Select-Object Name, Length | Format-Table
