param([string]$PhpPath = 'C:\xampp\php\php.exe')
$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $PhpPath)) { throw "PHP not found at $PhpPath. Pass -PhpPath with your PHP executable path." }
$adminName = Read-Host 'Administrator full name'
$adminUsername = Read-Host 'Username'
$adminSecret = Read-Host 'Password (at least 12 characters)' -AsSecureString
$adminConfirmation = Read-Host 'Confirm password' -AsSecureString
$secretPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($adminSecret)
$confirmPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($adminConfirmation)
try {
    $adminPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secretPointer)
    if ($adminPassword -cne [Runtime.InteropServices.Marshal]::PtrToStringBSTR($confirmPointer)) { throw 'Passwords do not match.' }
    $payload = @{ name=$adminName; username=$adminUsername; password=$adminPassword } | ConvertTo-Json -Compress
    $OutputEncoding = [System.Text.UTF8Encoding]::new($false)
    $payload | & $PhpPath (Join-Path $PSScriptRoot 'create-admin.php') --stdin
    if ($LASTEXITCODE -ne 0) { throw 'Administrator creation failed. See the message above.' }
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secretPointer)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($confirmPointer)
    $adminPassword = $null
    $payload = $null
    $adminSecret.Dispose()
    $adminConfirmation.Dispose()
}
