$file = 'd:\RBWStack\www\RBWManager\assets\css\style.css'
$c = Get-Content $file -Raw -Encoding UTF8
$c = $c -replace 'rgba\(168, 85, 247', 'rgba(255, 96, 0'
Set-Content $file $c -Encoding UTF8
Write-Host 'Done cleanup purple'
