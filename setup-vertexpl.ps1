$ErrorActionPreference = 'Stop'

$ProjectName = 'VertexPL'
$ProjectPath = Join-Path $PSScriptRoot $ProjectName
$ComposerPlatformPhp = '8.1.12'

function Assert-Command {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Name
    )

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required command '$Name' was not found in PATH."
    }
}

function Invoke-NativeCommand {
    param(
        [Parameter(Mandatory = $true)]
        [scriptblock]$Command,

        [Parameter(Mandatory = $true)]
        [string]$Description
    )

    Write-Host $Description -ForegroundColor Cyan
    & $Command

    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed with exit code $LASTEXITCODE."
    }
}

Write-Host 'Checking required tools...' -ForegroundColor Cyan
Assert-Command 'php'
Assert-Command 'composer'
Assert-Command 'node'
Assert-Command 'npm'

$phpVersionText = (& php -r "echo PHP_VERSION;").Trim()
if ($LASTEXITCODE -ne 0) {
    throw 'Unable to read the PHP version.'
}

$phpVersion = [version]$phpVersionText
Write-Host "PHP runtime version: $phpVersionText"

if ($phpVersion -lt [version]'7.4.0' -or $phpVersion -ge [version]'8.3.0') {
    throw "This VertexPL installer supports PHP 7.4 through PHP 8.2. Current version: $phpVersionText"
}

if ($phpVersion -ge [version]'8.2.0') {
    Write-Warning 'Laravel 8 officially supports PHP through 8.1. Installation will continue on PHP 8.2 with Composer platform pinned to PHP 8.1.12.'
}

if (Test-Path $ProjectPath) {
    throw "Project folder already exists: $ProjectPath. Rename or remove it before running this script."
}

Push-Location $PSScriptRoot
try {
    Invoke-NativeCommand -Description 'Creating Laravel 8 project...' -Command {
        composer create-project laravel/laravel $ProjectName '8.*' --prefer-dist --no-interaction
    }

    Set-Location $ProjectPath

    Invoke-NativeCommand -Description "Pinning Composer platform to PHP $ComposerPlatformPhp..." -Command {
        composer config platform.php $ComposerPlatformPhp
    }

    Invoke-NativeCommand -Description 'Resolving Laravel dependencies for the PHP 8.1 platform...' -Command {
        composer update --with-all-dependencies --no-interaction
    }

    Invoke-NativeCommand -Description 'Installing backend packages...' -Command {
        composer require 'laravel/sanctum:~2.11' 'spatie/laravel-permission:5.11.1' 'maatwebsite/excel:~3.1' 'doctrine/dbal:~3.9' 'guzzlehttp/guzzle:~7.0' --with-all-dependencies --no-interaction
    }

    Invoke-NativeCommand -Description 'Installing Laravel Breeze 1.9.2...' -Command {
        composer require laravel/breeze:1.9.2 --dev --with-all-dependencies --no-interaction
    }

    Invoke-NativeCommand -Description 'Checking actual PHP and extension requirements...' -Command {
        composer check-platform-reqs
    }

    Invoke-NativeCommand -Description 'Generating Breeze authentication scaffolding...' -Command {
        php artisan breeze:install
    }

    Invoke-NativeCommand -Description 'Installing frontend packages...' -Command {
        npm install
    }

    Invoke-NativeCommand -Description 'Installing SweetAlert2...' -Command {
        npm install 'sweetalert2@11'
    }

    Invoke-NativeCommand -Description 'Pinning Webpack for Laravel Mix compatibility...' -Command {
        npm install 'webpack@5.97.1' --save-dev
    }

    Invoke-NativeCommand -Description 'Publishing Spatie Permission files...' -Command {
        php artisan vendor:publish --provider='Spatie\Permission\PermissionServiceProvider'
    }

    Invoke-NativeCommand -Description 'Generating application key...' -Command {
        php artisan key:generate
    }

    $envPath = Join-Path $ProjectPath '.env'
    if (Test-Path $envPath) {
        $envContent = Get-Content $envPath -Raw
        $envContent = $envContent -replace '(?m)^APP_NAME=.*$', 'APP_NAME=VertexPL'
        $envContent = $envContent -replace '(?m)^DB_DATABASE=.*$', 'DB_DATABASE=vertex_pl'
        [System.IO.File]::WriteAllText(
            $envPath,
            $envContent,
            (New-Object System.Text.UTF8Encoding($false))
        )
    }

    Write-Host 'Copying VertexPL starter files...' -ForegroundColor Cyan
    $stubRoot = Join-Path $PSScriptRoot 'stubs'

    Copy-Item (Join-Path $stubRoot 'database\seeders\RolePermissionSeeder.php') `
        (Join-Path $ProjectPath 'database\seeders\RolePermissionSeeder.php') -Force

    Copy-Item (Join-Path $stubRoot 'database\seeders\DatabaseSeeder.php') `
        (Join-Path $ProjectPath 'database\seeders\DatabaseSeeder.php') -Force

    Copy-Item (Join-Path $stubRoot 'database\migrations\2026_01_01_000001_add_vertex_fields_to_users_table.php') `
        (Join-Path $ProjectPath 'database\migrations\2026_01_01_000001_add_vertex_fields_to_users_table.php') -Force

    Copy-Item (Join-Path $stubRoot 'app\Models\User.php') `
        (Join-Path $ProjectPath 'app\Models\User.php') -Force

    Invoke-NativeCommand -Description 'Building frontend assets...' -Command {
        npm run dev
    }

    Write-Host ''
    Write-Host 'VertexPL was created successfully.' -ForegroundColor Green
    Write-Host "Project path: $ProjectPath"
    Write-Host "PHP runtime: $phpVersionText"
    Write-Host "Composer platform PHP: $ComposerPlatformPhp"
    Write-Host ''
    Write-Host 'Next steps:' -ForegroundColor Yellow
    Write-Host '1. Create a MySQL database named vertex_pl.'
    Write-Host '2. Update database credentials in VertexPL\.env.'
    Write-Host '3. Set VERTEXPL_ADMIN_EMAIL and VERTEXPL_ADMIN_PASSWORD.'
    Write-Host '4. Run: php artisan migrate --seed'
    Write-Host '5. Run: php artisan serve'
}
finally {
    Pop-Location
}
