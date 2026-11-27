sap.ui.define([
    "sap/m/DynamicDateRange",
    "sap/m/DynamicDateRangeRenderer",
    "sap/m/DynamicDateFormat",
    "sap/m/DynamicDateOption",
    "sap/m/StandardListItem",
    "sap/m/StandardListItemRenderer",
    "sap/ui/Device",
    "sap/ui/core/Lib",
    "sap/ui/core/Renderer",
    "sap/ui/core/format/DateFormat",
    "sap/base/Log"
], function (DynamicDateRange, DynamicDateRangeRenderer, DynamicDateFormat, DynamicDateOption, StandardListItem,
    StandardListItemRenderer, Device, Lib, Renderer, DateFormat, Log) {
    "use strict";

    // Internal operators display saved ranges without adding entries to the options menu.
    const storedKey = "EXF_STORED_RANGE";
    const storedTimeKey = "EXF_STORED_TIME_RANGE";
    const internalFormat = "yyyy-MM-dd";
    const timeOptions = /^(?:DATETIME(?:RANGE)?|(?:LAST|NEXT)(?:MINUTES|HOURS)(?:INCLUDED)?)$/;
    const customGroupLabels = new WeakMap();
    const presetSelections = new WeakMap();

    const groupHeaderRenderer = Renderer.extend(StandardListItemRenderer);
    groupHeaderRenderer.getAccessibilityState = function (item) {
        return {role: "button", expanded: item.getExpanded()};
    };

    const GroupHeader = StandardListItem.extend("exface.ui5.InputDateRangeGroupHeader", {
        renderer: groupHeaderRenderer,
        metadata: {properties: {expanded: {type: "boolean", defaultValue: false}}},

        isSelectable: function () {
            return false;
        },

        onsapspace: function (event) {
            this.onsapenter(event);
            event.preventDefault();
        }
    });

    function setGroupExpanded(header, items, expanded) {
        header.setExpanded(expanded);
        header.setIcon(expanded ? "sap-icon://navigation-down-arrow" : "sap-icon://navigation-right-arrow");
        items.forEach(item => item.setVisible(expanded));
    }

    // UI5's native value is a selection object; exface binds to the scalar rangeValue instead.
    const InputDateRange = DynamicDateRange.extend("exface.ui5.InputDateRange", {
        renderer: DynamicDateRangeRenderer,
        metadata: {
            properties: {
                rangeValue: {type: "string", defaultValue: ""},
                rangeFormat: {type: "string", defaultValue: internalFormat},
                dateTimeRange: {type: "boolean", defaultValue: false},
                hideCalendarOption: {type: "boolean", defaultValue: false}
            }
        },

        init: function () {
            DynamicDateRange.prototype.init.call(this);
            const input = this.getAggregation("_input");
            this._calendarIcon = input.addEndIcon({
                src: "sap-icon://appointment-2",
                decorative: false,
                visible: false,
                tooltip: Lib.getResourceBundleFor("sap.m").getText("DYNAMIC_DATE_DATERANGE_TITLE"),
                press: () => this.openCalendar()
            });
            input.addEventDelegate({
                onsapspace: event => {
                    if (event.target !== input.getFocusDomRef() || event.isMarked()
                        || event.originalEvent?.isComposing || !this.getEnabled() || !this.getEditable()) {
                        return;
                    }
                    if (input.getValue() !== "") {
                        return;
                    }
                    event.preventDefault();
                    event.setMarked();
                    if (!event.originalEvent?.repeat && !this._oPopup?.isOpen()) {
                        input.onsapshow(event);
                    }
                }
            });
        },

        onBeforeRendering: function () {
            DynamicDateRange.prototype.onBeforeRendering.call(this);
            this._calendarIcon.setVisible(this.getEnabled() && this.getEditable() && !!this.getOption("DATERANGE"));
        },

        openCalendar: function () {
            if (!this.getEnabled() || !this.getEditable() || !this.getOption("DATERANGE") || this._oPopup?.isOpen()) {
                return;
            }
            const navigation = this._oNavContainer;
            const transition = navigation?.getDefaultTransitionName();
            this._openCalendarDirectly = true;
            try {
                // A reused popup may still be on the details page after dismissal.
                // Reset to the options page and open the calendar without queuing a slide.
                navigation?.setDefaultTransitionName("show");
                this.open();
            } finally {
                this._openCalendarDirectly = false;
                navigation?.setDefaultTransitionName(transition);
            }
        },

        open: function (domRef) {
            // Native open() rebuilds the menu and its group state each time.
            this._optionGroups = new WeakMap();
            this._currentOptionGroup = null;
            return DynamicDateRange.prototype.open.call(this, domRef);
        },

        _openPopup: function (domRef) {
            this._setOptionsPopupLayout?.(!this._openCalendarDirectly);
            if (this._openCalendarDirectly) {
                // Open the native range details page before the popup is shown,
                // so the user never has to pass through the options list.
                this._handleOptionPress({getSource: () => ({getOptionKey: () => "DATERANGE"})});
            } else if (this._currentOptionGroup
                && this._oOptionsList.getItems().filter(item => item instanceof GroupHeader).length === 1) {
                const {header, items} = this._currentOptionGroup;
                setGroupExpanded(header, items, true);
            }
            return DynamicDateRange.prototype._openPopup.call(this, domRef);
        },

        _applyNavContainerPageFocus: function (page) {
            // UI5's after-open handler always asks to focus the options page.
            const currentPage = this._oNavContainer.getCurrentPage();
            return DynamicDateRange.prototype._applyNavContainerPageFocus.call(this, currentPage || page);
        },

        _createPopupContent: function () {
            const firstOpen = !this._oNavContainer;
            const list = DynamicDateRange.prototype._createPopupContent.call(this);
            // Resize only the options page on desktop; keep the native phone layout.
            if (firstOpen && !Device.system.phone) {
                const popup = this._oPopup;
                const navigation = this._oNavContainer;
                const optionsPage = navigation.getPages()[0];
                const originalHeight = popup.getContentHeight();
                optionsPage.addStyleClass("exfDateRangeOptionsPage");
                this._setOptionsPopupLayout = showOptions => {
                    popup.toggleStyleClass("exfDateRangeOptionsPopup", showOptions);
                    popup.setContentHeight(showOptions ? "auto" : originalHeight);
                };
                navigation.attachNavigate(event => this._setOptionsPopupLayout(event.getParameter("to") === optionsPage));
            }
            return list;
        },

        _createHeaderListItem: function (title) {
            const items = [];
            const header = new GroupHeader({
                title,
                type: "Active",
                icon: "sap-icon://navigation-right-arrow",
                press: () => setGroupExpanded(header, items, !header.getExpanded())
            });
            this._currentOptionGroup = {header, items};
            return header;
        },

        _collectValueHelpItems: function (options, reduce) {
            const standard = [];
            const custom = new Map();
            for (const option of options) {
                // Keep DATERANGE registered for the icon and parsing, but omit its menu row.
                if (this.getHideCalendarOption() && option.getKey() === "DATERANGE") {
                    continue;
                }
                const label = customGroupLabels.get(option);
                if (label === undefined) {
                    standard.push(option);
                } else {
                    if (!custom.has(label)) {
                        custom.set(label, []);
                    }
                    custom.get(label).push(option);
                }
            }
            const items = [];
            // UI5 deduplicates headings by their display text. A custom label can
            // match a localized native heading but still represents a separate group.
            for (const [label, groupOptions] of custom) {
                if (this.getEnableGroupHeaders()) {
                    items.push(label);
                }
                items.push(...groupOptions);
            }
            return items.concat(DynamicDateRange.prototype._collectValueHelpItems.call(this, standard, reduce));
        },

        _createListItem: function (option) {
            const item = DynamicDateRange.prototype._createListItem.call(this, option);
            const group = this._currentOptionGroup;
            if (group) {
                item.setVisible(false);
                group.items.push(item);
                this._optionGroups.set(item, group.header);
            }
            return item;
        },

        _handleOptionPress: function (event) {
            const option = this.getOption(event.getSource().getOptionKey());
            const select = presetSelections.get(option);
            if (!select) {
                return DynamicDateRange.prototype._handleOptionPress.call(this, event);
            }
            // Native _applyValue assumes the option always returns a selection.
            const value = select();
            if (value) {
                const prevValue = this.getValue();
                this.setValue(value);
                this.fireChange({prevValue, value: this.getValue(), valid: true});
                this._closePopup();
            }
        },

        _determineOptionFocus: function (value) {
            const item = DynamicDateRange.prototype._determineOptionFocus.call(this, value);
            // When the selected option is collapsed, focus its group so it can be expanded.
            return item && !item.getVisible() ? this._optionGroups.get(item) : item;
        },

        _updateOptionsListAccessibilityAttributes: function () {
            // UI5's updater only recognizes non-interactive GroupHeaderListItems.
            // Leave positions to the list renderer, which uses visible items.
        },

        setRangeFormat: function (format) {
            this.setProperty("rangeFormat", format);
            return this.setFormatter(DynamicDateFormat.getInstance({date: {pattern: this.getRangeFormat()}}));
        },

        getOption: function (key) {
            if (key !== storedKey && key !== storedTimeKey) {
                return DynamicDateRange.prototype.getOption.call(this, key);
            }
            // Programmatically restored values must display even in a preset-only picker.
            return {
                getKey: () => key,
                getValueHelpUITypes: () => [],
                enhanceFormattedValue: () => false,
                format: value => value.values.map(date => key === storedTimeKey
                    ? DateFormat.getDateTimeInstance().format(date)
                    : exfTools.date.format(date, this.getRangeFormat())).join(" – "),
                toDates: value => value.values
            };
        },

        setRangeValue: function (value) {
            if (value == null || value === "") {
                this.setValue(undefined);
                this.setValueState("None");
                return this.setProperty("rangeValue", "");
            }
            // Exact timestamps restored from a saved filter must not be expanded
            // to whole days, even if the corresponding native option is no longer enabled.
            const range = exfTools.date.parseRange(String(value), this.getRangeFormat(), {dateTime: this.getDateTimeRange()});
            if (!range) {
                const message = Lib.getResourceBundleFor("sap.m").getText("DDR_WRONG_VALUE");
                this.setValue({operator: "PARSEERROR", values: [message, value]});
                this.setValueStateText(message);
                this.setValueState("Error");
                return this.setProperty("rangeValue", String(value));
            }
            const {dates, hasTime} = range;
            const exactTime = hasTime && exfTools.date.serializeRange(dates, true, true) !== exfTools.date.serializeRange(dates, true);
            const operator = exactTime ? storedTimeKey : (this.getOption("DATERANGE") ? "DATERANGE" : storedKey);
            this.setValue({operator, values: dates});
            this.setValueState("None");
            return this.setProperty("rangeValue", this.getExfValue());
        },

        _parseValue: function (text) {
            const parsed = DynamicDateRange.prototype._parseValue.call(this, text);
            if (parsed || !this.getOption("DATERANGE")) {
                return parsed;
            }
            // Fall back to the exface parser for shorthand and localized input,
            // but only when arbitrary ranges are enabled.
            // A single typed date is an inclusive range covering that day only.
            const range = exfTools.date.parseRange(text, this.getRangeFormat(), {allowSingle: true});
            return range ? {operator: "DATERANGE", values: range.dates} : null;
        },

        fireChange: function (parameters) {
            if (parameters.valid) {
                // Sync the bound scalar before listeners read it, leaving the
                // last valid bound value untouched on invalid edits.
                this.setValueState("None");
                const selection = this.getValue();
                const range = this.resolveExfValue(selection);
                // Native relative options recompute against "now" on every
                // toDates() call. Keep the bounds that were actually applied.
                this._committedRange = {selection, range};
                this.setProperty("rangeValue", range);
            } else {
                this.setValueStateText(parameters.value.values[0]);
                this.setValueState("Error");
            }
            return DynamicDateRange.prototype.fireChange.call(this, parameters);
        },

        getExfValue: function () {
            const value = this.getValue();
            const committed = this._committedRange;
            return committed && committed.selection === value
                ? committed.range
                : this.resolveExfValue(value);
        },

        resolveExfValue: function (value) {
            if (!value || value.operator === "PARSEERROR") {
                return "";
            }
            const dates = this.toDates(value);
            if (dates.length !== 2) {
                return "";
            }
            const jsDates = dates.map(date => date.getJSDate ? date.getJSDate() : date);
            const exactTime = value.operator === storedTimeKey || timeOptions.test(value.operator);
            return exfTools.date.serializeRange(jsDates, this.getDateTimeRange(), exactTime);
        },

        setExfValue: function (value) {
            const previous = this.getValue();
            this.setRangeValue(value);
            const selection = this.getValue();
            return this.fireChange({prevValue: previous, value: selection, valid: selection?.operator !== "PARSEERROR"});
        }
    });

    InputDateRange.getStandardOptionKeys = function (allowDefaultOptions, allowCustomRange) {
        if (!allowDefaultOptions) {
            return allowCustomRange ? ["DATERANGE"] : [];
        }

        const defaults = DynamicDateRange.getMetadata().getPropertyDefaults().standardOptions;
        // The scalar filter needs both bounds. Free-form calendar choices are
        // governed separately from native presets and relative options.
        const openEnded = ["FROM", "TO", "FROMDATETIME", "TODATETIME"];
        const calendarChoices = ["DATE", "DATETIME", "DATERANGE", "DATETIMERANGE"];
        return defaults.filter(key => !openEnded.includes(key)
            && (allowCustomRange || !calendarChoices.includes(key)));
    };

    InputDateRange.createPresetOptions = function (presets, format, weekStartsMonday) {
        const bundle = Lib.getResourceBundleFor("sap.m");
        // DynamicDateOption uses these group IDs to supply the native localized headings.
        const groupIds = new Map([["single_dates", 1], ["date_ranges", 2], ["weeks", 3], ["months", 4], ["quarters", 5], ["years", 6]]);
        return presets.flatMap((preset, index) => {
            const key = "EXF_RANGE_" + index;
            const [period, position] = preset.range.split("_");
            const prefix = {current: "THIS", previous: "LAST", next: "NEXT"}[position];
            const resolve = () => {
                const dates = exfTools.date.resolveRangePreset(preset, format, weekStartsMonday);
                if (!dates) {
                    Log.error("Invalid date range preset: " + (preset.caption || key),
                        "Check range_from and range_to.", "exface.ui5.InputDateRange");
                }
                return dates;
            };
            const initialDates = resolve();
            if (!initialDates) {
                return [];
            }
            const select = () => {
                const dates = resolve();
                return dates ? {operator: key, values: dates} : null;
            };
            const caption = preset.caption || (prefix
                ? bundle.getText("DYNAMIC_DATE_" + prefix + period.toUpperCase() + "_TITLE")
                : initialDates.map(date => exfTools.date.format(date, format)).join(" – "));
            // Resolve relative bounds on selection, not just at construction.
            // Store the resulting dates in the selection so reads stay stable.
            const groupId = groupIds.get(preset.group);
            const option = Object.assign(new DynamicDateOption({key, valueTypes: []}), {
                // UI5's sorter treats 0 as a group name; -1 sorts first but still gets the No Group label.
                getGroup: () => groupId ?? -1,
                getText: () => preset.hint ? caption + " — " + preset.hint : caption,
                getValueHelpUITypes: () => [],
                getValueHelpOutput: select,
                format: () => caption,
                parse: text => text === caption ? select() : null,
                toDates: value => value.values
            });
            presetSelections.set(option, select);
            if (preset.group && groupId === undefined) {
                customGroupLabels.set(option, preset.group);
            }
            return [option];
        });
    };

    return InputDateRange;
});
