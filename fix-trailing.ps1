$file = 'c:\Users\user\Local Sites\test\app\public\wp-content\plugins\osiriswp\includes\class-osiriswp.php'
$content = Get-Content $file -Raw
$content = $content.TrimEnd()
Set-Content $file -Value $content -NoNewline
Write-Host "Fixed trailing whitespace in class-osiriswp.php"
