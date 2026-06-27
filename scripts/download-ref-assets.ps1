$dest = Join-Path $PSScriptRoot '..\asset\home'
New-Item -ItemType Directory -Force -Path $dest | Out-Null

$files = @{
    'hero-bg.jpg' = 'https://white-express-glove.fwh.is/wp-content/uploads/revslider/home3/10_h.jpg'
    'dot-map.png' = 'https://white-express-glove.fwh.is/wp-content/uploads/2025/02/pngegg-20-e1739571015176.png'
    'why-choose-bg.jpg' = 'https://white-express-glove.fwh.is/wp-content/uploads/2019/01/12-2-1.jpg'
    'service-vehicle.jpg' = 'https://white-express-glove.fwh.is/wp-content/uploads/2025/12/medium-shot-smiley-woman-working-copy-scaled.jpg'
    'footer-cta-bg.webp' = 'https://white-express-glove.fwh.is/wp-content/uploads/2025/06/transport-logistics-products-copy-scaled-1.webp'
    'logo.webp' = 'https://white-express-glove.fwh.is/wp-content/uploads/2025/02/white-glove-1024x334.webp'
    'gallery-1.jpg' = 'https://white-express-glove.fwh.is/wp-content/uploads/2019/07/pics-300x213.jpg'
    'gallery-2.jpg' = 'https://white-express-glove.fwh.is/wp-content/uploads/2019/07/pics-300x213.jpg'
    'partner-dhl.png' = 'https://white-express-glove.fwh.is/wp-content/uploads/elementor/thumbs/DHLL-rg8osyr36ickuror5jacfzbkmqjho1peir0d5793hk.png'
    'partner-fedex.png' = 'https://white-express-glove.fwh.is/wp-content/uploads/elementor/thumbs/FEDXX-rg8oszoxdcdv6dne01oz0h3184euvqt4uvnumh7pbc.webp'
    'partner-usps.png' = 'https://white-express-glove.fwh.is/wp-content/uploads/elementor/thumbs/USPSS-rg8oszoxdcdv6dne01oz0h3184euvqt4uvnumh7pbc.webp'
    'partner-ups.png' = 'https://white-express-glove.fwh.is/wp-content/uploads/elementor/thumbs/UPSS-rg8osyr36ickuror5jacfzbkmqjho1peir0d5793hk.webp'
    'partner-amazon.png' = 'https://white-express-glove.fwh.is/wp-content/uploads/elementor/thumbs/AMZZ-rg8osyr36ickuror5jacfzbkmqjho1peir0d5793hk.webp'
    'partner-msc.png' = 'https://white-express-glove.fwh.is/wp-content/uploads/elementor/thumbs/MSC-rg8osyr36ickuror5jacfzbkmqjho1peir0d5793hk.webp'
}

foreach ($entry in $files.GetEnumerator()) {
    $out = Join-Path $dest $entry.Key
    Write-Host "Downloading $($entry.Key)..."
    Invoke-WebRequest -Uri $entry.Value -OutFile $out -UseBasicParsing
}

Get-ChildItem $dest | Format-Table Name, Length
