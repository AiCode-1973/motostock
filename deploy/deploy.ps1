# ============================================================
# MotoStock · pacote e envio para https://motostock.aicode.dev.br
# ============================================================
# Uso (PowerShell, na pasta do projeto):
#   .\deploy\deploy.ps1 -SomentePacote   → só gera deploy\dist\ (para enviar pelo Gerenciador de Arquivos do cPanel)
#   .\deploy\deploy.ps1                  → gera, envia por FTP e roda o extrator no servidor
#
# Pré-requisitos:
#   deploy\.deploy.env      (copie de deploy\deploy.env.example)   — credenciais FTP, fora do Git
#   deploy\.env.production  (copie de deploy\env.production.example) — só é usado na PRIMEIRA instalação
# ============================================================
param([switch]$SomentePacote)

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root = Split-Path $PSScriptRoot -Parent
$cfg = @{}
$cfgFile = Join-Path $PSScriptRoot '.deploy.env'
if (Test-Path $cfgFile) {
    Get-Content $cfgFile | Where-Object { $_ -match '^[^#].*=' } | ForEach-Object {
        $k, $v = $_ -split '=', 2; $cfg[$k.Trim()] = $v.Trim()
    }
}
if (-not $SomentePacote -and -not (Test-Path $cfgFile)) {
    Write-Host 'ERRO: crie deploy\.deploy.env a partir de deploy\deploy.env.example (ou use -SomentePacote).' -ForegroundColor Red; exit 1
}
$token = $cfg['DEPLOY_TOKEN']
if (-not $token -or $token.Length -lt 24) {
    $bytes = New-Object byte[] 24; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $token = ([Convert]::ToBase64String($bytes) -replace '[+/=]', 'x')
}
$appUrl = if ($cfg['APP_URL']) { $cfg['APP_URL'].TrimEnd('/') } else { 'https://motostock.aicode.dev.br' }

# ── 1. Testes ─────────────────────────────────────────────────────────────────
Write-Host '[1/4] Rodando os testes...' -ForegroundColor Yellow
Push-Location $root
& php bin/test.php | Select-Object -Last 3
$testsOk = ($LASTEXITCODE -eq 0)
Pop-Location
if (-not $testsOk) { Write-Host 'ERRO: testes falharam. Nada foi enviado.' -ForegroundColor Red; exit 1 }

# ── 2. Pacote (caminhos com "/" para o Linux do servidor) ─────────────────────
Write-Host '[2/4] Montando o pacote...' -ForegroundColor Yellow
$dist = Join-Path $PSScriptRoot 'dist'
New-Item -ItemType Directory -Force $dist | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmm'
$zipPath = Join-Path $dist 'motostock-pacote.zip'
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
function Add-Entry([string]$file, [string]$entry) {
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file, $entry, 'Optimal') | Out-Null
}
foreach ($dir in @('app', 'bin', 'database', 'public')) {
    Get-ChildItem (Join-Path $root $dir) -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($root.Length + 1) -replace '\\', '/'
        if ($rel -ne 'bin/seed-demo.php' -and $rel -ne 'bin/test.php' -and $rel -ne 'public/router.php') { Add-Entry $_.FullName $rel }
    }
}
foreach ($d in @('logs', 'uploads', 'exports', 'backups')) { Add-Entry (Join-Path $root "storage\$d\.gitkeep") "storage/$d/.gitkeep" }
Add-Entry (Join-Path $PSScriptRoot 'htaccess.production') '.htaccess'
Add-Entry (Join-Path $root '.env.example') '.env.example'
Add-Entry (Join-Path $root 'README.md') 'README.md'
$envProd = Join-Path $PSScriptRoot '.env.production'
if (Test-Path $envProd) {
    Add-Entry $envProd '.env.production'
    Write-Host '      .env.production incluído (só é usado se o servidor ainda não tiver .env)'
}
$zip.Dispose()
$extractor = Join-Path $dist 'motostock-extrair.php'
# Sem BOM: um BOM antes de "<?php" quebra o declare(strict_types=1) no servidor.
$php = [System.IO.File]::ReadAllText((Join-Path $PSScriptRoot 'extrair.php'), [System.Text.Encoding]::UTF8).Replace('__DEPLOY_TOKEN__', $token)
[System.IO.File]::WriteAllText($extractor, $php, (New-Object System.Text.UTF8Encoding($false)))
$size = [math]::Round((Get-Item $zipPath).Length / 1KB)
Write-Host "      $zipPath ($size KB)" -ForegroundColor Green
Write-Host "      $extractor"
Write-Host "      Token do extrator: $token"

if ($SomentePacote) {
    Write-Host ''
    Write-Host 'Envie os dois arquivos para a pasta do domínio e abra:' -ForegroundColor Cyan
    Write-Host "  $appUrl/motostock-extrair.php?token=$token"
    exit 0
}

# ── 3. FTP ────────────────────────────────────────────────────────────────────
Write-Host '[3/4] Enviando por FTP...' -ForegroundColor Yellow
$ftpPath = $cfg['FTP_PATH'].TrimEnd('/')
function Send-Ftp([string]$local, [string]$remote) {
    $req = [System.Net.FtpWebRequest]::Create("ftp://$($cfg['FTP_HOST'])$remote")
    $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $req.Credentials = New-Object System.Net.NetworkCredential($cfg['FTP_USER'], $cfg['FTP_PASS'])
    $req.UseBinary = $true; $req.UsePassive = $true; $req.EnableSsl = ($cfg['FTP_TLS'] -eq 'true')
    $bytes = [System.IO.File]::ReadAllBytes($local)
    $req.ContentLength = $bytes.Length
    $s = $req.GetRequestStream(); $s.Write($bytes, 0, $bytes.Length); $s.Close()
    $req.GetResponse().Close()
}
Send-Ftp $zipPath "$ftpPath/motostock-pacote.zip"
Send-Ftp $extractor "$ftpPath/motostock-extrair.php"
Write-Host '      Enviado.' -ForegroundColor Green

# ── 4. Extração no servidor ───────────────────────────────────────────────────
Write-Host '[4/4] Extraindo no servidor...' -ForegroundColor Yellow
$url = "$appUrl/motostock-extrair.php?token=$token"
try {
    $resp = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 300
    ($resp.Content -replace '<[^>]+>', "`n") -split "`n" | Where-Object { $_.Trim() } | ForEach-Object { Write-Host "      $_" }
} catch {
    Write-Host "Não consegui confirmar. Abra no navegador: $url" -ForegroundColor Yellow
}
Remove-Item $extractor -Force
Write-Host ''
Write-Host "Pronto: $appUrl" -ForegroundColor Cyan
