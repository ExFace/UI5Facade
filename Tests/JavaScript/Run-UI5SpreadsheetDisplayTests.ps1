# Run from any directory; uses the installed UI5 theme and shared renderer.
param([string]$Browser = "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe")
$ErrorActionPreference = 'Stop'
$app = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$core = [IO.Path]::GetFullPath((Join-Path $app '..\core'))
$temporary = Join-Path $env:TEMP ('exf-ui5-display-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temporary | Out-Null
try {
    if (-not (Test-Path $Browser)) { throw 'Pass -Browser with the path to an installed Chromium-based browser.' }
    $shared = (ConvertTo-Json -InputObject ([IO.File]::ReadAllText((Join-Path $core 'Facades\AbstractAjaxFacade\Elements\JExcelDisplayTrait.php'))) -Compress).Replace('<', '\u003c')
    $ui5 = (ConvertTo-Json -InputObject ([IO.File]::ReadAllText((Join-Path $app 'Facades\Elements\Traits\UI5JExcelTrait.php'))) -Compress).Replace('<', '\u003c')
    $tests = [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'UI5SpreadsheetDisplay.test.js'))
    $colors = (New-Object System.Uri((Join-Path $core 'Facades\AbstractAjaxFacade\js\exfColorTools.js'))).AbsoluteUri
    $css = (New-Object System.Uri((Join-Path $app 'Facades\js_openui5\resources\sap\m\themes\sap_fiori_3\library.css'))).AbsoluteUri
    $html = @"
<!doctype html><html><head><link rel="stylesheet" href="$css"></head><body>
<pre id="result">RUNNING</pre><script src="$colors"></script><script>
$tests
try {
    document.getElementById('result').textContent = runUI5SpreadsheetDisplayTests($shared, $ui5);
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
    if ($process.ExitCode -ne 0 -or $result -notmatch '^\d+ UI5 spreadsheet checks passed$') {
        throw "Browser checks did not pass: $result"
    }
    Write-Output $result
} finally {
    Remove-Item -LiteralPath $temporary -Recurse -Force -ErrorAction SilentlyContinue
}
