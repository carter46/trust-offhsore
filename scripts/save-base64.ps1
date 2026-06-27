param(
    [Parameter(Mandatory = $true)][string]$Base64,
    [Parameter(Mandatory = $true)][string]$OutFile
)

$bytes = [Convert]::FromBase64String($Base64)
$dir = Split-Path -Parent $OutFile
if ($dir -and -not (Test-Path $dir)) {
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
}
[IO.File]::WriteAllBytes($OutFile, $bytes)
Write-Host "Wrote $OutFile ($($bytes.Length) bytes)"
