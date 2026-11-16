# Developer's documentation for the OpenUI5 facade

## Overview

## Details

1. Widgets
2. [Routing](routing.md)
3. [Views](Views.md)
4. [Controllers](Controllers.md)
5. [Translations](translations.md)
6. [Modifications made to UI5](UI5_modifications.md) - things, that could not be handled "outside" and needed a hack.

## JSON and UXON inputs

`UI5InputJson` and `UI5InputUxon` use Core's `JsonEditorTrait` and
`exfJsonEditor` adapter. UXON inputs provide the same autosuggest, templates,
presets, property details, model browser and clipboard tools as jEasyUI.

The native `exface.ui5Custom.JsonEditor` control has a bindable string `value`
property and a `change` event. Standard UI5 binding modes, named models and
relative row contexts are supported. Model updates do not fire `change`;
user edits update the property and fire it once per edit. Empty JSON objects
and arrays represent empty widget values. Invalid JSON remains editable in
code mode and survives rerendering.

The control owns and destroys the adapter, including requests and popups.
Rerendering restores the current value and code-mode choice. Its `enabled`,
`required`, `valueState` and `valueStateText` properties integrate with the
usual input validation and conditional properties. UXON popups are registered
with UI5's popup focus handling and layered above UI5 dialogs.

For a local browser regression, serve `vendor/` as a static document root,
open [the fixture](../../Tests/JavaScript/JsonEditor.html), and run:

```javascript
await (await import('./UI5JsonEditor.mjs')).runUI5JsonEditorRegression();
```