/* Runs with the real DOM, UI5 theme CSS and exfColorTools.js. */
function runUI5SpreadsheetDisplayTests(sSharedSource, sUI5Source) {
    var iChecks = 0;
    function check(sName, mActual, mExpected) {
        iChecks++;
        if (mActual !== mExpected) throw new Error(sName + ': expected ' + mExpected + ', got ' + mActual);
    }
    var shared = sSharedSource.match(/return <<<'JS'\r?\n([\s\S]*?)\r?\nJS;/);
    var ui5 = sUI5Source.match(/protected function buildJsJExcelDisplayStyler[\s\S]*?return <<<JS\r?\n([\s\S]*?)\r?\nJS;/);
    if (!shared || !ui5) throw new Error('Spreadsheet generators not found');
    var oRenderer = new Function('return ' + shared[1])();
    var sStyler = ui5[1]
        .replace(/\{\$this->escapeString\(\$textColors\)\}/g, JSON.stringify({'#8c8278': '#ffffff', '#dd8585': '#000000'}))
        .replace(/\{\$this->escapeString\(\$preference\)\}/g, '0.5');
    var fnStyler = new Function('return ' + sStyler)();
    var oDisplay = {
        type: 'color', colorScale: {}, colorRange: false, colorColumn: null,
        color: '#8c8278', colorOnly: false, fill: true,
        hint: '', hintScale: {}, hintRange: false,
        format: function(v) { return v; }, style: fnStyler
    };
    var oWidget = {
        _displayRenderer: oRenderer, _displaySemanticColors: {'~ERROR': '#ff0000'},
        getColumnModel: function() { return {display: oDisplay}; },
        getJExcel: function() { return {options: {data: []}}; }
    };
    var domCell = document.createElement('td');
    domCell.className = 'readonly exf-spreadsheet-change';
    domCell.title = 'Validation message';
    var table = document.createElement('table');
    var row = table.insertRow();
    row.appendChild(domCell);
    document.body.appendChild(table);
    function render(value) {
        oRenderer.renderDisplayCell.call(oWidget, domCell, 0, 0, value);
        return domCell.firstElementChild;
    }
    function text(dom) { return dom.querySelector('.sapMObjStatusText'); }
    function wrapper(dom) { return dom.querySelector('.sapMObjStatusWrapper'); }
    try {
        var badge = render('Flächensicherung');
        check('UI5 status badge', badge.classList.contains('sapMObjStatusInverted'), true);
        check('text retained', text(badge).textContent, 'Flächensicherung');
        check('dark scale uses ObjectStatus foreground', text(badge).style.color, 'rgb(255, 255, 255)');
        check('rounded theme corners', parseFloat(getComputedStyle(wrapper(badge)).borderTopLeftRadius) > 0, true);
        check('background on rounded wrapper only', badge.style.backgroundColor, '');
        check('scale background', wrapper(badge).style.backgroundColor, 'rgb(140, 130, 120)');
        oDisplay.color = '#dd8585';
        badge = render('Kreuzungsvereinbarung');
        check('light scale foreground', text(badge).style.color, 'rgb(0, 0, 0)');
        ['#000000', '#ffffff', 'rgb(0, 0, 0)', 'navy'].forEach(function(color) {
            oDisplay.color = color;
            badge = render('Dynamic color');
            var expected = document.createElement('span');
            expected.style.color = exfColorTools.pickTextColorForBackgroundColor(color, 0.5);
            check('dynamic foreground ' + color, text(badge).style.color, expected.style.color);
        });
        oDisplay.color = '~ERROR';
        check('semantic color resolution', wrapper(render('Error')).style.backgroundColor, 'rgb(255, 0, 0)');
        oDisplay.color = 'red';
        oDisplay.fill = false;
        badge = render('Text only');
        check('unfilled status is not inverted', badge.classList.contains('sapMObjStatusInverted'), false);
        check('unfilled status has colored text', text(badge).style.color, 'red');
        check('unfilled status has no background', wrapper(badge).style.backgroundColor, '');
        oDisplay.fill = true;
        badge = render('<img src=x onerror=alert(1)>');
        check('safe text', text(badge).textContent, '<img src=x onerror=alert(1)>');
        check('no HTML injection', badge.querySelector('img'), null);
        check('grid change markers preserved', domCell.className, 'readonly exf-spreadsheet-change');
        check('grid validation title preserved', domCell.title, 'Validation message');
        oDisplay.colorOnly = true;
        badge = render('Hidden label');
        check('color only is round', badge.style.borderRadius, '50%');
        check('color only is compact', badge.style.width, '1em');
        check('color only has accessible hint', badge.getAttribute('aria-label'), 'Hidden label');
        check('color only background', badge.style.backgroundColor, 'red');
        check('color only hides label', badge.textContent, '\u00a0');
        oDisplay.style = null;
        oDisplay.colorOnly = false;
        badge = render('Shared renderer');
        check('other facades retain shared rendering', badge.classList.contains('sapMObjStatus'), false);
        check('shared background unchanged', badge.style.backgroundColor, 'red');
        Object.assign(oDisplay, {
            style: fnStyler, color: null, colorColumn: null, colorScale: ['lightsteelblue', 'steelblue'],
            format: function(value) { return Number(value) === 1 ? 'Yes' : 'No'; }
        });
        [0, 1, '0', '1', false, true].forEach(function(value) {
            badge = render(value);
            var color = Number(value) === 1 ? 'steelblue' : 'lightsteelblue';
            var expected = document.createElement('span');
            expected.style.backgroundColor = color;
            expected.style.color = exfColorTools.pickTextColorForBackgroundColor(color, 0.5);
            check('Boolean badge background', wrapper(badge).style.backgroundColor, expected.style.backgroundColor);
            check('Boolean badge contrast', text(badge).style.color, expected.style.color);
            check('Boolean badge localized label', text(badge).textContent, Number(value) === 1 ? 'Yes' : 'No');
        });
        badge = render(null);
        check('unmatched scale is not inverted', badge.classList.contains('sapMObjStatusInverted'), false);
        check('unmatched scale has no forced white text', text(badge).style.color, '');
        Object.assign(oDisplay, {
            type: 'progress', style: null, color: 'steelblue', colorScale: {},
            min: 0, max: 100, text: null, textColumn: null, textScale: {}
        });
        [0, 50, 100].forEach(function(value) {
            var progress = render(value);
            check('progress rounded border ' + value, parseFloat(getComputedStyle(progress).borderTopLeftRadius) > 0, true);
            check('progress rounded fill ' + value, getComputedStyle(progress.firstElementChild).borderTopLeftRadius, getComputedStyle(progress).borderTopLeftRadius);
            check('progress clipped fill ' + value, getComputedStyle(progress).overflow, 'hidden');
            check('progress width retained ' + value, progress.firstElementChild.style.width, value + '%');
        });
        oDisplay.format = function(value) { return value; };
        ['#107e3e', '#ffffff'].forEach(function(color) {
            oDisplay.color = color;
            [0, 35, 100].forEach(function(value) {
                var progress = render(value);
                var label = progress.querySelector('.exf-spreadsheet-progress-text');
                var inside = color === '#107e3e' ? 'rgb(255, 255, 255)' : 'rgb(0, 0, 0)';
                var expected = document.createElement('span');
                expected.style.backgroundImage = 'linear-gradient(to right, ' + inside + ' ' + value + '%, currentColor ' + value + '%)';
                check('split progress foreground ' + color + '/' + value, label.style.backgroundImage, expected.style.backgroundImage);
                check('foreground clips to glyphs', getComputedStyle(label).backgroundClip, 'text');
                check('progress label not duplicated', label.textContent, String(value));
                check('progress gradient aligns with fill', Math.abs(label.getBoundingClientRect().width - progress.clientWidth) <= 1, true);
            });
        });
        domCell.style.color = 'white';
        domCell.style.backgroundColor = 'black';
        oDisplay.color = '#ffffff';
        oDisplay.textColorPreference = 0;
        var themedProgress = render(35);
        var themedLabel = themedProgress.querySelector('.exf-spreadsheet-progress-text');
        var themedExpected = document.createElement('span');
        themedExpected.style.backgroundImage = 'linear-gradient(to right, rgb(255, 255, 255) 35%, currentColor 35%)';
        check('progress respects preference and dark-theme foreground', themedLabel.style.backgroundImage, themedExpected.style.backgroundImage);
        check('unfilled text inherits dark-theme foreground', getComputedStyle(themedLabel).color, 'rgb(255, 255, 255)');
        domCell.style.color = 'black';
        check('unfilled text follows foreground changes without rerender', getComputedStyle(themedLabel).color, 'rgb(0, 0, 0)');
        return iChecks + ' UI5 spreadsheet checks passed';
    } finally {
        table.remove();
    }
}
