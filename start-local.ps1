$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
php artisan serve --host=127.0.0.1 --port=8095
