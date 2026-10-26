param([string]$Browser = "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe")
$ErrorActionPreference = 'Stop'
$app = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$vendor = Split-Path (Split-Path $app)
$temporary = Join-Path $env:TEMP ('exf-ui5-padding-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temporary | Out-Null
try {
    if (-not (Test-Path $Browser)) { throw 'Pass -Browser with the path to a Chromium browser.' }
    $tests = [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'UI5SpreadsheetPadding.test.js'))
    $styles = @(
        'npm-asset\jsuites\dist\jsuites.css',
        'npm-asset\jspreadsheet-ce\dist\jspreadsheet.css',
        'exface\ui5facade\Facades\js_openui5\resources\sap\m\themes\sap_fiori_3\library.css',
        'exface\ui5facade\Facades\js_openui5\resources\sap\ui\table\themes\sap_fiori_3\library.css',
        'exface\ui5facade\Facades\js\openui5.template.css'
    ) | ForEach-Object {
        $uri = (New-Object System.Uri((Join-Path $vendor $_))).AbsoluteUri
        '<link rel="stylesheet" href="' + $uri + '">'
    }
    $suites = (New-Object System.Uri((Join-Path $vendor 'npm-asset\jsuites\dist\jsuites.js'))).AbsoluteUri
    $grid = (New-Object System.Uri((Join-Path $vendor 'npm-asset\jspreadsheet-ce\dist\index.js'))).AbsoluteUri
    $html = @"
<!doctype html><html class="sapUiTheme-sap_fiori_3"><head>$($styles -join "`n")</head><body>
<pre id="result">RUNNING</pre><script src="$suites"></script><script src="$grid"></script><script>
$tests
try {
    document.getElementById('result').textContent = runUI5SpreadsheetPaddingTests();
} catch (error) {
    document.getElementById('result').textContent = 'FAILED: ' + error.stack;
}
</script></body></html>
"@
    $page = Join-Path $temporary 'test.html'
    [IO.File]::WriteAllText($page, $html)
    $uri = (New-Object System.Uri($page)).AbsoluteUri
    $process = Start-Process -FilePath $Browser -ArgumentList @(
        '--headless', '--disable-gpu', '--no-first-run', '--disable-extensions',
        "--user-data-dir=`"$temporary\profile`"", '--dump-dom', $uri
    ) -RedirectStandardOutput (Join-Path $temporary 'output.html') -RedirectStandardError (Join-Path $temporary 'stderr.txt') -Wait -PassThru
    $output = [IO.File]::ReadAllText((Join-Path $temporary 'output.html'))
    $result = [regex]::Match($output, '<pre id="result">([\s\S]*?)</pre>').Groups[1].Value
    if ($process.ExitCode -ne 0 -or $result -notmatch '^\d+ UI5 spreadsheet padding checks passed$') {
        throw "Browser checks did not pass: $result"
    }
    Write-Output $result
} finally {
    Remove-Item -LiteralPath $temporary -Recurse -Force -ErrorAction SilentlyContinue
}