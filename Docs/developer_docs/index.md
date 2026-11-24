# Developer's documentation for the OpenUI5 facade

## Overview

## Details

1. Widgets
2. [Routing](routing.md)
3. [Views](Views.md)
4. [Controllers](Controllers.md)
5. [Translations](translations.md)
6. [Modifications made to UI5](UI5_modifications.md) - things, that could not be handled "outside" and needed a hack.

## Live widget-link values

Value getters accept one coordinate argument: a string, Core's
`WidgetLinkCoordinateInterface`, or `null`. Forward widget links with
`$link->getTargetCoordinate()` rather than extracting their column or row.

Value getters normalize their argument with
`WidgetLinkFactory::createCoordinateFromAnything()` and call
`validateValueGetterCoordinate()` before generating JavaScript. The base hook
supports scalar values; data controls override it for their row scopes and custom
coordinates. Wrappers can forward to a getter that performs this validation.
Keep missing-column errors where the coordinate's column is resolved.

Data widgets read raw loaded rows, not the action-data projection:

- `AMOUNT$2` reads the zero-based row index, preserving zero and false values.
- `AMOUNT$~loaded` reads the column from all loaded rows; unloaded pages are not fetched.
- `AMOUNT$~selected` reads the logical selection, including saved selections from other pages.
- `AMOUNT$~loaded:SUM` and `AMOUNT$~selected:LIST_DISTINCT` aggregate the requested scope
  through the shared Core aggregation helper, using the column's value-list delimiter.

Without a column or aggregator, data getters still return rows; aggregation
without a column uses the UID column. Without a row scope, getters use
the logical selection. `~rowcount` still counts loaded rows. Explicit scopes
update on refresh; all/selected scopes also update on changes, including edits.
Editable data widgets dispatch only coordinate-bound callbacks on cell edits,
through their dedicated `coordinateEdit` handler; unrelated refresh callbacks
are not invoked.
Unknown row scopes are rejected. Scalar controls reject row scopes and aggregators.
Combo-table inputs support selected values without aggregation; input-button result
columns support numbered rows. Scheduler custom coordinates do not support row scopes
or aggregators.

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