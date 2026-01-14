param(
    [string]$RepoRoot = (Resolve-Path ".").Path
)

$variablesFile = Join-Path $RepoRoot "resources\sass\abstracts\_variables.scss"
if (-not (Test-Path $variablesFile)) {
    throw "Variables file not found: $variablesFile"
}

$varNames = Get-Content -Path $variablesFile | ForEach-Object {
    if ($_ -match '^\s*\$([A-Za-z0-9_-]+)\s*:') { $Matches[1] }
} | Where-Object { $_ } | Sort-Object -Unique

$files = @(Get-ChildItem -Path (Join-Path $RepoRoot "resources\sass") -Recurse -Filter "*.scss" | ForEach-Object FullName)

$bootstrapScss = Join-Path $RepoRoot "node_modules\bootstrap\scss"
if (Test-Path $bootstrapScss) {
    $files += @(Get-ChildItem -Path $bootstrapScss -Recurse -Filter "*.scss" | ForEach-Object FullName)
}

$unused = New-Object System.Collections.Generic.List[string]

foreach ($var in $varNames) {
    $pattern = "\$" + [regex]::Escape($var) + "\b"

    $hits = Select-String -Path $files -Pattern $pattern -AllMatches -ErrorAction SilentlyContinue |
        Where-Object { $_.Path -ne $variablesFile }

    if (($hits | Measure-Object).Count -eq 0) {
        $unused.Add("$" + $var)
    }
}

$unused | Sort-Object
