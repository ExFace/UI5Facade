# MultiLevelNavItem: Implementation and Usage Notes

## Scope

This document records the implemented approach for enabling multi-level entries in
the side navigation while preserving native OpenUI5 layout and styling.

## Key Findings

1. Rendering with custom markup/class names breaks native theme styling.
2. The safest approach is to keep stock rendering output and only override behavior.
3. For deeper levels, stock logic blocks expansion via a level-0 check.
4. `onAfterRendering` is not a reliable hook for `NavigationListItem` (item/element lifecycle).
5. Depth indentation must be applied in the renderer path on the hovered element (`_renderStartLink` / anchor), otherwise hover background can animate/paint differently than the indented area.
6. The stock slide animation in `NavigationList._animateExpandCollapse()` only walks two levels deep (`getItems().flatMap(i => [i, ...i.getItems()])`). Items nested at level 3+ that set `_animateExpand` are never picked up, so the `sapTntNLIItemsContainerHidden` class added by the renderer is never removed and the children stay invisible. Skipping the animation flags entirely makes the hidden class purely driven by `expanded`, which works at any depth (at the cost of losing the slide animation).

## Current Implementation

### File: Facades/js/ui5Custom/MultiLevelNavItem.js

- Extends `sap.tnt.NavigationListItem` as `exface.ui5Custom.MultiLevelNavItem`.
- Overrides `items` aggregation type to self-reference this class.
- Overrides `getLevel()` to walk ancestor chain recursively using `isA("sap.tnt.NavigationListItem")` checks.
- Overrides `render()` to always call the stock first-level renderer:
   `NavigationListItem.prototype.renderFirstLevelNavItem.call(this, rm, control)`.
- Overrides `renderMainElement()` to keep stock structure/classes/ARIA and custom multi-level behavior compatible with current UI5 rendering.
- Overrides `_renderStartLink()` to apply indentation on the anchor (`<a>`) element itself, fixing hover background mismatch on padded levels.
- Overrides `_handleExpanderClick()` to remove stock level-0 restriction.
- Overrides `expand()` and `collapse()` to allow expansion for all levels and to
   deliberately omit the `_animateExpand`/`_animateCollapse` flags. Reasoning:
   the stock `NavigationList._animateExpandCollapse()` only sees items at depth
   0 and 1, so flagged items at depth 2+ never get their hidden class removed.
   Without the flag the renderer's class decision reduces to `!expanded`, which
   correctly shows/hides children at every depth. Trade-off: no slide animation.
- Uses render-time indentation (no `onAfterRendering` dependency).

### File: Facades/Templates/OpenUI5AppTemplate.html

- Registers module path:
   `jQuery.sap.registerModulePath('libs.exface.ui5Custom', 'vendor/exface/ui5facade/Facades/js/ui5Custom')`
- Requires module in init chain:
   `libs/exface/ui5Custom/MultiLevelNavItem`

### File: Facades/Elements/UI5NavMenu.php

- Builds tree with `new exface.ui5Custom.MultiLevelNavItem(...)`.
- Applies depth cap via `maxDepth` (default `3`).

## Usage

1. Keep the module registration and `sap.ui.require` call in the app template.
2. Use `MultiLevelNavItem` for all levels in `UI5NavMenu`.
3. Control rendered depth with `setMaxDepth(int $depth)`.
4. Recommended start value: `3`.

## Indentation Behavior

- Indentation step is `0.75rem` per level.
- Applied inside `_renderStartLink()` using `RenderManager.style(...)`.
- Styles applied: `padding-left` and `padding-inline-start`.
- Applied only when:
   - level > 0,
   - navigation list is expanded,
   - item is not inside the collapsed-mode popup.
- Level is computed recursively; expected effective values are:
   - Level 0: `0rem`
   - Level 1: `0.75rem`
   - Level 2: `1.5rem`
   - Level 3: `2.25rem`

## Known Limitations

1. Collapsed side navigation popup still shows only one sub-level (stock behavior).
2. Parent-node and leaf-node selection behavior remains stock.
3. Any major OpenUI5 changes in internal class names may require re-checking
   the custom `renderMainElement()` override.
4. Expand/collapse is instant (no slide animation), because the stock animation
   handler does not reach items below depth 1. See finding 6.

## Verification Checklist

1. Expand menu to level 3 and confirm left offsets increase per depth.
2. Confirm icons/text alignment remains consistent with standard `sap.tnt.NavigationListItem`.
3. Confirm expand/collapse icons work on level 1+ parents.
4. Confirm collapsed mode still opens popup for level-0 entries.
5. Confirm hover background fills the indented area uniformly (no delayed strip at the left).
6. Expand a level-3 (or deeper) parent and confirm its children appear immediately (regression check for the stock 2-level animation limitation).

## References

- SAP Community Part 2: https://community.sap.com/t5/-/-/ba-p/13320528
- SAP Community Part 3: https://community.sap.com/t5/-/-/m-p/13320749
- OpenUI5 source: https://github.com/SAP/openui5/blob/master/src/sap.tnt/src/sap/tnt/NavigationListItem.js
