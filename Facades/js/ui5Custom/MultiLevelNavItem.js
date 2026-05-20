/**
 * MultiLevelNavItem – a custom extension of sap.tnt.NavigationListItem that supports
 * recursive nesting beyond the standard two-level limit.
 *
 * ## Background
 *
 * The standard `sap.tnt.NavigationListItem` only supports two levels of depth:
 *   - Level 0 – direct child of NavigationList (shows icon + text)
 *   - Level 1 – child of a level-0 item (shows indented text only)
 *
 * Any items nested deeper than level 1 are silently dropped by the renderer.
 * This custom control overrides the rendering pipeline to support arbitrary depth
 * while retaining full compatibility with `sap.tnt.SideNavigation`, including
 * collapsed icon-only mode and expand/collapse behaviour.
 *
 * ## Usage
 *
 * Register this file as an external module in the UI5 controller and use
 * `exface.ui5Custom.MultiLevelNavItem` everywhere `sap.tnt.NavigationListItem`
 * would normally appear. The public API (addItem / getItems / setExpanded / etc.)
 * is identical to the standard control.
 *
 * ## Implementation Notes 
 *
 * The override strategy follows the technique described by Musa Arda in the SAP
 * Community blog series (2017) and adapts it for broader compatibility:
 *
 *  1. The `items` aggregation type is overridden in metadata to accept
 *     `exface.ui5Custom.MultiLevelNavItem` instead of `sap.tnt.NavigationListItem`,
 *     enabling self-referencing recursive trees.
 *
 *  2. `getLevel()` is replaced with a while-loop that walks up the full ancestor
 *     chain, so it returns the correct depth for items nested at level 2+.
 *
 *  3. `render()` always delegates to `renderFirstLevelNavItem()` regardless of the
 *     actual level. The standard implementation would call `renderSecondLevelNavItem`
 *     for level >= 1, which does not support sub-items.
 *
 *  4. `renderFirstLevelNavItem()` is rewritten to iterate `getItems()` recursively,
 *     rendering the full sub-tree.
 *
 *  5. `renderGroupItem()` adds a `padding-left` style calculated as
 *     `level * 0.75rem` to visually indent deeper levels.
 *
 *  6. `expand()` and `collapse()` are overridden to toggle only the *direct* child
 *     `<ul>` element. The standard implementation uses jQuery `.find()`, which
 *     matches all descendant `<ul>` elements and causes incorrect collapse of
 *     nested groups.
 *
 *  7. `_select()` adds the selection CSS class only when the item has no children
 *     (leaf nodes). Parent items are expand/collapse toggles, not navigable targets.
 *
 *  8. `ontap()` uses a uniform handler for all nesting levels.
 *
 * ## Known Limitations
 *
 *  - Collapsed sidebar popup shows only the first sub-level. Deeper levels are not
 *    accessible from the popup (standard SideNavigation limitation).
 *  - Uses the modern RenderManager API (rm.openStart / rm.attr / rm.openEnd / rm.close /
 *    rm.class / rm.style / rm.accessibilityState). Compatible with UI5 >= 1.67.
 *
 * @see PLAN_MultiLevelNavItem.md for architecture decisions and future work.
 * @see https://community.sap.com/t5/-/-/ba-p/13320528  (blog part 2)
 * @see https://community.sap.com/t5/-/-/m-p/13320749   (blog part 3)
 */
sap.ui.define([
    "sap/tnt/NavigationListItem",
    "sap/tnt/library",
    "sap/ui/core/library",
    "sap/ui/util/defaultLinkTypes"
], function (NavigationListItem, tntLibrary, coreLibrary, defaultLinkTypes) {
    "use strict";

    var NavigationListItemDesign = tntLibrary.NavigationListItemDesign;
    var AriaHasPopup = coreLibrary.aria.HasPopup;
    var EXPAND_ICON_SRC = "sap-icon://navigation-right-arrow";
    var COLLAPSE_ICON_SRC = "sap-icon://navigation-down-arrow";

    var MultiLevelNavItem = NavigationListItem.extend("exface.ui5Custom.MultiLevelNavItem", {

        metadata: {
            library: "exface.ui5Custom",

            /**
             * Override the inherited `items` aggregation to accept our own type,
             * making the tree self-referencing and enabling unlimited nesting.
             * The singular/plural names and accessor methods (addItem, getItems, …)
             * remain identical to the standard control.
             */
            aggregations: {
                items: {
                    type: "exface.ui5Custom.MultiLevelNavItem",
                    multiple: true,
                    singularName: "item"
                }
            }
        },

        /* =========================================================== */
        /* Level helpers                                                */
        /* =========================================================== */

        /**
         * Returns the nesting depth of this item (0-based).
         *
         * The standard implementation only checks one parent up, returning at
         * most 1. This override walks the full ancestor chain so that items at
         * level 2+ receive the correct level number for indentation and ARIA.
         *
         * @returns {number} 0 for top-level items, 1 for their children, etc.
         */
        getLevel: function () {
            var level = 0;
            var parent = this.getParent();
            // Count all ancestor NavigationListItem nodes, regardless of concrete subclass.
            // In some flows parent instances may resolve to sap.tnt.NavigationListItem,
            // which made strict class-name checks flatten deeper levels to the same indent.
            while (parent && parent.isA && parent.isA("sap.tnt.NavigationListItem")) {
                level++;
                parent = parent.getParent();
            }
            return level;
        },

        /* =========================================================== */
        /* Rendering                                                    */
        /* =========================================================== */

        /**
         * Entry point called by NavigationListItemRenderer for each item.
         *
         * Standard behaviour: route to `renderFirstLevelNavItem` for level 0,
         * `renderSecondLevelNavItem` for level >= 1. The second-level renderer
         * does not support sub-items, so we bypass it entirely and always call
         * `renderFirstLevelNavItem`, which we have extended to handle recursion.
         *
         * @param {sap.ui.core.RenderManager} rm
         * @param {sap.tnt.NavigationList} control  The parent NavigationList.
         * @param {number} index   Position of this item among its siblings.
         * @param {number} length  Total number of siblings.
         */
        render: function (rm, control, index, length) {
            if (!this.getVisible()) {
                return;
            }
            // Always use the stock first-level renderer to preserve native classes and layout,
            // then recurse through this control's overridden render() for deeper levels.
            NavigationListItem.prototype.renderFirstLevelNavItem.call(this, rm, control);
        },

        /**
         * Renders the main clickable container using stock structure/classes,
         * but adds level-based indentation at render time for all depths.
         *
         * @param {sap.ui.core.RenderManager} oRM
         * @param {sap.tnt.NavigationList} oNavigationList
         * @param {string} sSubtreeId
         */
        renderMainElement: function (oRM, oNavigationList, sSubtreeId) {
            var bListExpanded = this._isListExpanded();
            var aItems = this._getVisibleItems(this);
            var bDisabled = !this.getEnabled() || !this.getAllParentsEnabled();
            var bExpanded = this.getExpanded();
            var bSelectable = this.getSelectable();
            var sDesign = this.getDesign();
            var bExpanderVisible = !!aItems.length && this.getHasExpander();
            var bExternalLink = this.getHref() && this.getTarget() === "_blank";

            oRM.openStart("div")
                .class("sapTntNLI")
                .class("sapTntNLIFirstLevel");

            if (bDisabled) {
                oRM.class("sapTntNLIDisabled");
            }

            if (bExternalLink) {
                oRM.class("sapTntNLIExternalLink");
            }

            var bSelected = false;
            if (bSelectable && oNavigationList._selectedItem === this) {
                oRM.class("sapTntNLISelected");
                bSelected = true;
            }

            if ((!bListExpanded || !bExpanded) && aItems.includes(oNavigationList._selectedItem)) {
                oRM.class("sapTntNLISelected");
                bSelected = true;
            }

            if (bExpanderVisible) {
                oRM.class("sapTntNLIWithExpander");
            }

            if (bSelectable && aItems.length) {
                oRM.class("sapTntNLITwoClickAreas");
            }

            var oLinkAriaProps = {};

            if (this.getAriaHasPopup() !== AriaHasPopup.None) {
                oLinkAriaProps.haspopup = this.getAriaHasPopup();
            }

            if (sDesign === NavigationListItemDesign.Action) {
                oRM.class("sapTntNLIAction");
            }

            if (!bSelectable) {
                oRM.class("sapTntNLIUnselectable");
            }

            if (this._isInsidePopover()) {
                oRM.class("sapTntNLIInPopover");
            }

            if (!bListExpanded) {
                oLinkAriaProps.role = bSelectable ? "menuitemradio" : "menuitem";

                if (aItems.length) {
                    oLinkAriaProps.haspopup = "tree";
                }

                if (this._isOverflow) {
                    oLinkAriaProps.haspopup = "menu";
                    oLinkAriaProps.label = this._resourceBundleTnt.getText("NAVIGATION_LIST_OVERFLOW_ITEM_LABEL");
                }

                if (bSelectable) {
                    oLinkAriaProps.checked = oNavigationList._selectedItem === this;
                    oLinkAriaProps.selected = bSelected;
                } else {
                    oLinkAriaProps.selected = false;
                }

                oLinkAriaProps.roledescription = this._resourceBundleTnt.getText("NAVIGATION_LIST_ITEM_ROLE_DESCRIPTION_MENUITEM");
            } else {
                if (this.getSelectable() && this.getItems().length) {
                    oLinkAriaProps.describedby = this._getInvisibleDescriptionLinkText().getId();
                }

                oLinkAriaProps.role = "treeitem";

                if (bSelectable) {
                    oLinkAriaProps.selected = bSelected;
                } else {
                    oLinkAriaProps.selected = false;
                }

                if (bSelected) {
                    oLinkAriaProps.current = "page";
                }

                if (aItems.length) {
                    oLinkAriaProps.owns = sSubtreeId;
                    oLinkAriaProps.expanded = bExpanded;
                }
            }

            oRM.openEnd();

            this._renderStartLink(oRM, oLinkAriaProps, bDisabled);

            this._renderIcon(oRM);

            this._renderText(oRM);

            if (bExternalLink) {
                this._renderExternalLinkIcon(oRM);
            }

            if (bListExpanded) {
                var oIcon = this._getExpandIconControl();
                oIcon.setVisible(bExpanderVisible)
                    .setSrc(bExpanded ? COLLAPSE_ICON_SRC : EXPAND_ICON_SRC)
                    .setTooltip(this._getExpandIconTooltip(!bExpanded));

                oRM.renderControl(oIcon);
            }

            if (!bListExpanded && this.getItems().length) {
                var oCollapsedIcon = this._getExpandIconControl().setSrc(EXPAND_ICON_SRC);
                oRM.renderControl(oCollapsedIcon);
            }

            this._renderCloseLink(oRM);

            oRM.close("div");
        },

        /**
         * Renders opening tag of anchor element.
         *
         * Overrides stock method to apply indentation on the anchor itself,
         * so hover background and indented area animate/paint consistently.
         *
         * @param {sap.ui.core.RenderManager} oRM renderer instance
         * @param {object} oAriaProps object with aria properties
         * @param {boolean} bDisabled whether the item is disabled
         * @private
         */
        _renderStartLink: function (oRM, oAriaProps, bDisabled) {
            var sHref = this.getHref();
            var sTarget = this.getTarget();

            oRM.openStart("a", this.getId() + "-a")
                .accessibilityState(this, {
                    ...oAriaProps
                });

            var sTooltip = this.getTooltip_AsString();
            if (sTooltip) {
                oRM.attr("title", sTooltip);
            }

            if (bDisabled) {
                oRM.attr("aria-disabled", "true");
            }

            oRM.attr("tabindex", "-1");

            if (sHref) {
                oRM.attr("href", sHref);
            }

            if (sTarget) {
                oRM.attr("target", sTarget)
                    .attr("rel", defaultLinkTypes("", sTarget));
            }

            if (sHref && sTarget === "_blank") {
                var oInvisibleText = NavigationListItem._getInvisibleText();
                oRM.attr("aria-describedby", oInvisibleText.getId());
            }

            // Apply indentation to the hovered element itself (the link), not the wrapper.
            if (this.getLevel() > 0 && this._isListExpanded() && !this._isInsidePopover()) {
                var sPad = (this.getLevel() * 0.75) + "rem";
                oRM.style("padding-left", sPad);
                oRM.style("padding-inline-start", sPad);
            }

            oRM.openEnd();
        },

        /**
         * Handles expander click for all levels (stock limits to level 0).
         *
         * @param {sap.ui.base.Event} oEvent tap event
         * @returns {boolean}
         * @private
         */
        _handleExpanderClick: function (oEvent) {
            var sClickTargetClassName = this._getExpanderActivationTarget();
            var oClickedRef = oEvent.target.closest(sClickTargetClassName);
            if (!this._isListExpanded() || !oClickedRef) {
                return false;
            }

            if ((oEvent.key ? oEvent.key === "Enter" : oEvent.keyCode === 13)
                || (oEvent.key ? oEvent.key === " " : oEvent.keyCode === 32)) {
                return false;
            }

            if (this.getExpanded()) {
                this.collapse();
            } else {
                this.expand();
            }

            return true;
        },

        /* =========================================================== */
        /* Expand / Collapse                                            */
        /* =========================================================== */

        /**
         * Expands this item to reveal its children.
         *
         * IMPORTANT: We deliberately do NOT set `_animateExpand` here. The stock
         * `NavigationList._animateExpandCollapse` only walks two levels deep
         * (`getItems().flatMap(i => [i, ...i.getItems()])`), so for items nested
         * at level 3+ the slide animation never runs. The renderer would then
         * leave the `sapTntNLIItemsContainerHidden` class in place forever,
         * making deeper children invisible. By skipping the animation flag we
         * lose the slide animation but the children show/hide reliably at every
         * depth because the hidden class is then purely driven by `expanded`.
         *
         * @returns {boolean} true on success, false if already expanded or no children.
         */
        expand: function () {
            if (!this.getEnabled() || this.getExpanded() || !this.getHasExpander() || this.getItems().length === 0) {
                return false;
            }

            this.setProperty("expanded", true);
            return true;
        },

        /**
         * Collapses this item, hiding its children.
         *
         * See `expand()` for why the `_animateCollapse` flag is intentionally
         * not set.
         *
         * @returns {boolean} true on success, false if already collapsed or no children.
         */
        collapse: function () {
            if (!this.getEnabled() || !this.getExpanded() || !this.getHasExpander() || this.getItems().length === 0) {
                return false;
            }

            this.setProperty("expanded", false);
            return true;
        }

    }); // end extend

    return MultiLevelNavItem;
});
