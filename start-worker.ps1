$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
php artisan queue:work --sleep=3 --tries=1 --timeout=240 --max-time=3600
