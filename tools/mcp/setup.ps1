param()
$ErrorActionPreference = 'Stop'
$checkout = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
Set-Location -LiteralPath $checkout
if (!(Test-Path -LiteralPath .env)) {
    $password = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
    $key = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    @"
APP_NAME="Invoice Ninja MCP Demo"
APP_ENV=local
APP_KEY=base64:$key
APP_DEBUG=false
APP_URL=http://app:8000
DB_CONNECTION=mysql
DB_HOST=db
DB_DATABASE=ninja_mcp_test
DB_USERNAME=ninja
DB_PASSWORD=$password
MCP_TEST_DB_PASSWORD=$password
DB_PORT=3306
DB_STRICT=false
MULTI_DB_ENABLED=false
NINJA_ENVIRONMENT=selfhost
TRAVIS=true
MCP_ENABLED=true
MAIL_MAILER=array
BROADCAST_DRIVER=log
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
SCOUT_DRIVER=null
REDIS_HOST=redis
PDF_GENERATOR=simulator
REQUIRE_HTTPS=false
SENTRY_SAMPLE_RATE=0
SENTRY_TRACES_SAMPLE_RATE=0
SENTRY_PROFILES_SAMPLE_RATE=0
"@ | Set-Content -LiteralPath .env -Encoding utf8
}
if (!(Select-String -LiteralPath .env -Pattern '^DB_DATABASE=ninja_mcp_test$' -Quiet)) { throw 'Existing .env must explicitly target ninja_mcp_test; it was preserved.' }
New-Item -ItemType Directory -Force -Path .local | Out-Null
function Invoke-CheckedDocker {
    param([string[]]$DockerArguments)
    & docker @DockerArguments
    if ($LASTEXITCODE -ne 0) { throw 'Docker step failed; remaining dependent steps were not run.' }
}
Invoke-CheckedDocker -DockerArguments @('compose','--env-file','.env','-f','tools/mcp/compose.yaml','build','app')
Invoke-CheckedDocker -DockerArguments @('volume','create','invoiceninja-mcp-test-vendor')
$taskComposerAuth = $env:COMPOSER_AUTH
try {
    if (!$env:COMPOSER_AUTH -and (Get-Command gh -ErrorAction SilentlyContinue)) {
        & gh auth status *> $null
        if ($LASTEXITCODE -eq 0) { $env:COMPOSER_AUTH = @{ 'github-oauth' = @{ 'github.com' = (& gh auth token) } } | ConvertTo-Json -Compress }
    }
    Invoke-CheckedDocker -DockerArguments @('run','--rm','--mount',"type=bind,source=$checkout,target=/app",'--mount','type=volume,source=invoiceninja-mcp-test-vendor,target=/app/vendor','--mount','type=volume,source=invoiceninja-mcp-test-composer-cache,target=/root/.cache/composer','-e','COMPOSER_AUTH','invoiceninja-mcp-test-app','composer','install','--no-interaction','--prefer-dist','--no-scripts')
} finally {
    if ($null -eq $taskComposerAuth) { Remove-Item Env:\COMPOSER_AUTH -ErrorAction SilentlyContinue } else { $env:COMPOSER_AUTH = $taskComposerAuth }
}
Invoke-CheckedDocker -DockerArguments @('compose','--env-file','.env','-f','tools/mcp/compose.yaml','up','-d','db','redis')
Invoke-CheckedDocker -DockerArguments @('compose','--env-file','.env','-f','tools/mcp/compose.yaml','run','--rm','app','composer','dump-autoload','--no-interaction')
Invoke-CheckedDocker -DockerArguments @('compose','--env-file','.env','-f','tools/mcp/compose.yaml','run','--rm','app','php','artisan','migrate','--force')
Invoke-CheckedDocker -DockerArguments @('compose','--env-file','.env','-f','tools/mcp/compose.yaml','run','--rm','app','php','artisan','db:seed','--force')
if (!(Test-Path -LiteralPath .local/fixture.json)) { Invoke-CheckedDocker -DockerArguments @('compose','--env-file','.env','-f','tools/mcp/compose.yaml','run','--rm','app','php','tools/mcp/seed.php') }
Invoke-CheckedDocker -DockerArguments @('run','--rm','--mount',"type=bind,source=$checkout/tools/mcp,target=/work",'--workdir','/work','node:22-bookworm-slim','npm','ci','--no-audit','--no-fund')
Invoke-CheckedDocker -DockerArguments @('compose','--env-file','.env','-f','tools/mcp/compose.yaml','up','-d','app')
Write-Output 'Synthetic app ready on the internal Docker network. Credentials and outcomes are in ignored .local files.'
