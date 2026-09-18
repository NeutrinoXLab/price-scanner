$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
php artisan schedule:work
