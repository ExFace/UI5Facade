# Markdown widgets

`InputMarkdown` uses Markian; `Markdown` and `DisplayMarkdown` use its independent
sanitized viewer. There is no Toast UI fallback. Install the declared
`axenox/markian` dependency with a Core providing `MarkianTrait`.
Core's public `ToastUIEditorTrait` has been removed; upgrade Core and UI5Facade
together. Remove installation/user overrides of `LIBS.TOASTUI.EDITOR.JS` and
`.CSS`. Custom integrations must use `MarkianTrait` and own their viewer lifecycle.

Asset URLs are configured by `LIBS.MARKIAN.*`. The Code extension supplies syntax
highlighting; Mermaid fences use the optional Markian editor/viewer extensions
with `LIBS.PELE.JS`. Display-only pages do not load the editor bundle.

Editor labels, menus and Help follow the current session language.
`LIBS.MARKIAN.TRANSLATIONS` points to the vendor-relative dictionary directory
(`axenox/markian/Translations/` by default). The session locale is reduced to its
language using `LocaleDataType`, so `de_DE` loads only `de.json` when it exists.
English is embedded and is never requested; unavailable translations retain English.
Selected file loading errors still fail editor readiness. Changing locale requires
a new editor instance. Viewer content and saved Markdown are not translated.

The native `exface.ui5Custom.Markdown` control owns value bindings, conditional
enabled/required states and validation. Model loads do not fire user-change
events. Value-bound tooltip changes update the host directly without rebuilding
the editor or disturbing focus. Validation messages take precedence over hints.
UI5 rerenders preserve active source and dispose the previous instance;
destruction also restores fullscreen content and releases observers.
Fullscreen registers with UI5 modal focus handling and tracks the visual viewport.
Viewers retain Markdown source, base URLs and configured link targets.

The editor uses UI5 theme variables for its font family and size, field and
button corners, borders, colours and focus indicators. Fiori 3 and Horizon,
including their dark variants, follow the current theme without rebuilding the
editor. Compact/cozy toolbar sizes, 16px icons and text padding match native UI5
controls. Fullscreen retains the editor's density; display-only viewers are not
given editable-field borders or padding.
Inline code and code blocks use a subtle theme-aware background distinct from
the document surface in light and dark themes.
Read-only viewers use the same text size, line height, link colours and code
styling, with native UI5 text colours and a transparent, unpadded document surface.
The editor and popup text fields also use UI5's native field underline styling,
including its theme-defined colour rather than a hard-coded black line.
Toolbar button/dropdown hover states match native transparent UI5 buttons:
Fiori 3 adds a hover border, while Horizon changes only the background.
Popup fields, checkboxes and dropdown menu rows follow the same theme and density.
Checkboxes retain native label/keyboard toggling and use the UI5 checkmark.
Markdown task checkboxes share that styling and align with the first text line,
including wrapped items. Viewer tasks remain disabled but retain readable colours.
Select fields keep browser keyboard selection with a UI5 dropdown indicator.
Horizon's inset field border and the focus indicator are painted above the editor
surfaces so opaque Visual/Source content cannot hide them.
When Markdown is the last field in the final responsive form group, the group's
extra bottom padding is omitted. The form/card padding and editor height remain
unchanged.

Inputs start in Visual mode regardless of legacy `editor_mode`; users can still
switch to Source. Stencil insertion controls are unfinished and emit a warning.
`allow_images: false` removes the image toolbar item and rejects raster insertion,
but does not strip existing images or reject manually entered image Markdown.
Sanitized viewer output may omit unsupported raw HTML. Stored Markdown is not
rewritten. These are the same current library limits as the
[jEasyUI integration](https://github.com/ExFace/JEasyUIFacade/blob/master/Docs/Developer_docs/Markdown_widgets.md).

## Validation

The focused PHP generation tests are `UI5MarkianTest` in the PHPUnit unit suite.
Serve the installation's vendor directory over HTTP and open
`Tests/JavaScript/Markdown.html`; run the exported
`runUI5MarkdownRegression()` from `UI5Markdown.mjs`. It exercises native UI5
bindings, model reset, editor/viewer rerenders, validation, disabled editing,
fullscreen focus and disposal against the packaged Markian assets.

`runUI5MarkdownThemeRegression()` from `UI5MarkdownTheme.mjs` compares rendered
editor/viewer fonts, code styling, padding, corners, toolbar sizes and icons against native UI5 controls in
Fiori 3/Horizon, light/dark and compact/cozy modes, including narrow containers
and fullscreen.

`runUI5MarkdownBorderRegression()` from `UI5MarkdownBorder.mjs` checks that the
border/focus layer stays above Visual and Source surfaces, preserves validation
styling and does not intercept clicks in either theme's light/dark variants.

`runUI5MarkdownSpacingRegression()` from `UI5MarkdownSpacing.mjs` checks that only
a final Markdown field loses extra form-group spacing, without changing editor
heights, values or spacing for other fields.

`runUI5MarkdownHoverRegression(fnHover)` from `UI5MarkdownHover.mjs` compares real
pointer hover on buttons, dropdown triggers and disabled controls against native
UI5 toolbars in both themes' light/dark variants. Supply an asynchronous browser
automation callback that hovers the supplied CSS selector.

`runUI5MarkdownPopupRegression()` from `UI5MarkdownPopup.mjs` compares popup
inputs, checkbox boxes/checkmarks and menu rows against native controls across
both themes, densities and light/dark variants. It also checks the real link
heading selector and preserves search input and the editor draft during theme changes.

`runUI5MarkdownTaskRegression()` from `UI5MarkdownTasks.mjs` compares editor and
viewer task boxes/checkmarks with native checkboxes and measures first-line text
alignment for wrapped, nested and loose lists across themes and densities.
It also checks Preview styling, alignment and read-only task permissions.

For the value-bound tooltip regression, start `runUI5MarkdownTooltipRegression()`
from `UI5MarkdownTooltip.mjs`, type `Focus check` into its editor, then call
`window.ui5MarkdownTooltipFixture.finish()`. The returned promise checks that
typing retains focus, the complete source and the same editor instance, while
tooltip updates preserve validation feedback.
