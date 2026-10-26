function runUI5SpreadsheetPaddingTests() {
    var checks = 0;
    function check(name, actual, expected) {
        checks++;
        if (actual !== expected) throw new Error(name + ': expected ' + expected + ', got ' + actual);
    }
    var host = document.createElement('div');
    // UI5DataImporter and older generated grids only have the common UI5 class.
    host.className = 'sapUiTable';
    host.style.width = '1200px';
    document.body.appendChild(host);
    var reference = document.createElement('table');
    reference.className = 'sapUiTable';
    reference.innerHTML = '<tbody><tr><td class="sapUiTableDataCell"><div class="sapUiTableCellInner"><span class="sapMText">First line\nSecond line\nThird line</span></div></td></tr></tbody>';
    document.body.appendChild(reference);
    var originalFont = document.documentElement.style.fontSize;
    var grid = jspreadsheet(host, {
        data: [['Text', 42, 'A', 'First line\nSecond line\nThird line', true, 'Read only']],
        columns: [
            {type: 'text', title: 'Text', width: 160},
            {type: 'numeric', title: 'Number', width: 160},
            {type: 'dropdown', title: 'Choice', width: 160, source: ['A', 'B']},
            {type: 'text', title: 'Wrapped', width: 160, wordWrap: true},
            {type: 'checkbox', title: 'Boolean', width: 160},
            {type: 'text', title: 'Readonly', width: 160, readOnly: true}
        ],
        columnSorting: false
    });
    function cell(index) { return grid.getCellFromCoords(index, 0); }
    function style(node) { return getComputedStyle(node); }
    function lines(node) {
        var range = document.createRange();
        range.selectNodeContents(node);
        return Array.prototype.filter.call(range.getClientRects(), function(rect) { return rect.width > 0; });
    }
    try {
        ['sapUiSizeCompact', 'sapUiSizeCozy'].forEach(function(density) {
            document.body.classList.add(density);
            ['16px', '20px'].forEach(function(fontSize) {
                document.documentElement.style.fontSize = fontSize;
                var inset = style(reference.querySelector('div')).paddingLeft;
                [0, 1, 3, 4, 5].forEach(function(index) {
                    check('body left inset ' + density + '/' + fontSize + '/' + index, style(cell(index)).paddingLeft, inset);
                    check('body right inset ' + index, style(cell(index)).paddingRight, inset);
                    check('values vertically centered ' + index, style(cell(index)).verticalAlign, 'middle');
                });
                check('headers align with values', style(host.querySelector('thead td[data-x="0"]')).paddingLeft, inset);
                check('row selector stays compact', style(host.querySelector('td.jexcel_row')).paddingLeft, '5px');
                check('dropdown keeps arrow space', style(cell(2)).paddingRight, '35px');
                check('checkbox is not given editor inset', style(cell(4).querySelector('input')).paddingLeft, '0px');
                var nativeLines = lines(reference.querySelector('.sapMText'));
                var gridLines = lines(cell(3));
                check('wrapped cell retains all three lines', gridLines.length, 3);
                check('wrapped cell follows native text line-height', style(cell(3)).lineHeight, style(reference.querySelector('.sapMText')).lineHeight);
                check('measured line spacing matches UI5 text', Math.abs((gridLines[1].top - gridLines[0].top) - (nativeLines[1].top - nativeLines[0].top)) < 1, true);
                check('wrapped text has top breathing room', gridLines[0].top - cell(3).getBoundingClientRect().top >= 4, true);
                check('wrapped text has bottom breathing room', cell(3).getBoundingClientRect().bottom - gridLines[2].bottom >= 4, true);
                [0, 1, 3].forEach(function(index) {
                    var node = cell(index);
                    grid.openEditor(node);
                    var input = node.querySelector('input, textarea');
                    check('real editor created ' + index, input !== null, true);
                    check('editor has no double cell inset ' + index, style(node).paddingLeft, '0px');
                    check('editor left inset matches value ' + index, style(input).paddingLeft, inset);
                    check('editor right inset matches value ' + index, style(input).paddingRight, inset);
                    if (index === 3) {
                        check('multiline editor keeps native line-height', style(input).lineHeight, style(reference.querySelector('.sapMText')).lineHeight);
                        check('multiline editor top inset matches display', style(input).paddingTop, (parseFloat(fontSize) / 4) + 'px');
                    }
                    grid.closeEditor(node, false);
                    check('closed editor restores value inset ' + index, style(node).paddingLeft, inset);
                });
                grid.openEditor(cell(2));
                var dropdown = cell(2).querySelector('.jdropdown-header');
                check('real dropdown editor created', dropdown !== null, true);
                check('dropdown editor left inset', style(dropdown).paddingLeft, inset);
                check('dropdown editor arrow space', style(dropdown).paddingRight, '35px');
                check('dropdown has no outer horizontal padding', style(cell(2)).paddingRight, '0px');
                grid.closeEditor(cell(2), false);
                check('dropdown closed arrow space restored', style(cell(2)).paddingRight, '35px');
            });
            document.body.classList.remove(density);
        });
        host.classList.add('exf-spreadsheet-container');
        check('spreadsheet-specific wrapper also retains inset', style(cell(0)).paddingLeft, style(reference.querySelector('div')).paddingLeft);
        check('raw data unchanged by editing layout', grid.getData()[0][1], 42);
        return checks + ' UI5 spreadsheet padding checks passed';
    } finally {
        document.documentElement.style.fontSize = originalFont;
        document.body.classList.remove('sapUiSizeCompact', 'sapUiSizeCozy');
        jspreadsheet.destroy(host);
        host.remove();
        reference.remove();
    }
}
