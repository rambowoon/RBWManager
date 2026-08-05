$file = 'd:\RBWStack\www\RBWManager\assets\css\style.css'
$c = Get-Content $file -Raw -Encoding UTF8

# Root variables
$c = $c -replace '--primary: #a855f7', '--primary: #ff6000'
$c = $c -replace '--primary-hover: #9333ea', '--primary-hover: #e05500'
$c = $c -replace '--primary-glow: rgba\(168, 85, 247, 0\.45\)', '--primary-glow: rgba(255, 96, 0, 0.45)'
$c = $c -replace '--purple: #c084fc', '--purple: #fb00d3'
$c = $c -replace 'rgba\(168, 85, 247, 0\.15\)', 'rgba(255, 96, 0, 0.15)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.12\)', 'rgba(255, 96, 0, 0.15)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.18\)', 'rgba(255, 96, 0, 0.2)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.2\)', 'rgba(255, 96, 0, 0.2)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.22\)', 'rgba(255, 96, 0, 0.2)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.28\)', 'rgba(255, 96, 0, 0.25)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.3\)', 'rgba(255, 96, 0, 0.3)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.35\)', 'rgba(255, 96, 0, 0.35)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.38\)', 'rgba(255, 96, 0, 0.38)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.4\)', 'rgba(255, 96, 0, 0.4)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.45\)', 'rgba(255, 96, 0, 0.45)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.55\)', 'rgba(255, 96, 0, 0.55)'
$c = $c -replace 'rgba\(168, 85, 247, 0\.7\)', 'rgba(255, 96, 0, 0.7)'
$c = $c -replace 'rgba\(124, 58, 237, 0\.06\)', 'rgba(255, 96, 0, 0.06)'
$c = $c -replace 'rgba\(139, 92, 246, 0\.18\)', 'rgba(251, 0, 211, 0.18)'
$c = $c -replace 'rgba\(139, 92, 246, 0\.25\)', 'rgba(251, 0, 211, 0.25)'
$c = $c -replace 'rgba\(139, 92, 246, 0\.3\)', 'rgba(251, 0, 211, 0.3)'
$c = $c -replace 'rgba\(139, 92, 246, 0\.4\)', 'rgba(251, 0, 211, 0.4)'
$c = $c -replace 'rgba\(139, 92, 246, 0\.5\)', 'rgba(251, 0, 211, 0.5)'
$c = $c -replace 'rgba\(139, 92, 246, 0\.7\)', 'rgba(251, 0, 211, 0.7)'
$c = $c -replace 'rgba\(182, 68, 255, 0\.2\)', 'rgba(251, 0, 211, 0.2)'
$c = $c -replace 'rgba\(182, 68, 255, 0\.3\)', 'rgba(251, 0, 211, 0.3)'

# Solid colors
$c = $c -replace '#a855f7', '#ff6000'
$c = $c -replace '#9333ea', '#e05500'
$c = $c -replace '#c084fc', '#fb00d3'
$c = $c -replace '#B644FF', '#fb00d3'
$c = $c -replace '#7C6FF5', '#e05500'
$c = $c -replace '#d8a4ff', '#ffb3f0'

# Gradients - replace old purple-orange ones with new orange-pink
$c = $c -replace 'linear-gradient\(135deg, #ff6000 0%, #f97316 100%\)', 'linear-gradient(217deg, #ff6000, #fb00d3)'
$c = $c -replace 'linear-gradient\(135deg, #ff6000, #f97316\)', 'linear-gradient(217deg, #ff6000, #fb00d3)'
$c = $c -replace 'linear-gradient\(90deg, #ff6000, #f97316\)', 'linear-gradient(217deg, #ff6000, #fb00d3)'
$c = $c -replace 'linear-gradient\(120deg, #ff6000, #f97316\)', 'linear-gradient(217deg, #ff6000, #fb00d3)'
$c = $c -replace 'inset 3px 0 0 #ff6000', 'inset 3px 0 0 #fb00d3'
$c = $c -replace 'inset 2px 0 0 #ff6000', 'inset 2px 0 0 #fb00d3'
$c = $c -replace 'linear-gradient\(to bottom, #ff6000, #7C6FF5\)', 'linear-gradient(217deg, #ff6000, #fb00d3)'

# Tab, history active gradient
$c = $c -replace 'linear-gradient\(120deg, #a855f7, #f97316\)', 'linear-gradient(217deg, #ff6000, #fb00d3)'

Set-Content $file $c -Encoding UTF8
Write-Host 'Done replacing colors'
