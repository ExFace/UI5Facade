/**
 * Custom P13n panels filter tab and advanced search tab.
 * TODO move this to the ui5custom folder. Maybe save a separate files?
 */
(function () {
	"use strict";

	/** @lends sap.m.sample.P13nDialogWithCustomPanel.CustomPanel */
	sap.m.P13nPanel.extend("exface.openui5.P13nLayoutPanel", {
		constructor: function (sId, mSettings) {
			sap.m.P13nPanel.apply(this, arguments);
		},
		metadata: {
			library: "sap.m",
			aggregations: {
				content: {
					type: "sap.ui.core.Control",
					multiple: false,
					singularName: "content"
				}
			}
		},
		renderer: function (oRm, oControl) {
			if (!oControl.getVisible()) {
				return;
			}
			oRm.renderControl(oControl.getContent());
		}
	});

	sap.m.P13nPanel.extend("exface.openui5.P13AdvancedSearchPanel", {
		metadata: {
			library: "sap.m",
			properties: {
				modelName: { type: "string", defaultValue: "configurator" },
				dataTableId: { type: "string", defaultValue: "" },
				includeTitle: { type: "string", defaultValue: "Include" },
				excludeTitle: { type: "string", defaultValue: "Exclude" },
				logicalOperatorText: { type: "string", defaultValue: "AND" },
				headerFilterTooltip: { type: "string", defaultValue: "This filter comes from a column header." }
			},
			aggregations: {
				content: {
					type: "sap.ui.core.Control",
					multiple: false,
					singularName: "content"
				}
			},
			events: {
				conditionChange: {}
			}
		},

		constructor: function (sId, mSettings) {
			sap.m.P13nPanel.apply(this, arguments);
			this.setContent(this._createContent());
		},

		renderer: function (oRm, oControl) {
			if (oControl.getVisible()) {
				oRm.renderControl(oControl.getContent());
			}
		},

		beforeNavigationTo: function () {
			sap.m.P13nPanel.prototype.beforeNavigationTo.apply(this, arguments);
			this._ensureGroupRows();
		},

		_createContent: function () {
			var oPanel = this;
			var sModel = this.getModelName();
			var sPrefix = sModel + ">";
			var fnNotify = function (oEvent) {
				var oSource = oEvent.getSource();
				var oContext = oSource.getBindingContext(sModel);
				var oCondition = oContext ? oContext.getObject() : null;
				if (oCondition && oCondition.linked_to_header === true) {
					oPanel._syncHeaderFilter(oCondition, false);
				}
				oPanel.fireConditionChange();
			};
			var fnCreateList = function (bExclude) {
				return new sap.m.List({
					showSeparators: sap.m.ListSeparators.Inner,
					items: {
						path: sPrefix + "/advanced_search",
						filters: [new sap.ui.model.Filter("exclude", sap.ui.model.FilterOperator.EQ, bExclude)],
						templateShareable: false,
						template: new sap.m.CustomListItem({
						content: new sap.m.HBox({
							width: "100%",
							alignItems: sap.m.FlexAlignItems.Center,
							wrap: sap.m.FlexWrap.Wrap,
							items: [
								new sap.m.Text({
									width: "3rem",
									text: oPanel.getLogicalOperatorText()
								}).addStyleClass("sapUiTinyMarginEnd sapUiTinyMarginBottom exf-p13n-advanced-search-operator"),
								new sap.m.Select({
									width: "16rem",
									enabled: "{= !${" + sPrefix + "linked_to_header} }",
									selectedKey: "{" + sPrefix + "expression}",
									items: {
										path: sPrefix + "/searchables",
										templateShareable: false,
										template: new sap.ui.core.Item({
											key: "{" + sPrefix + "attribute_alias}",
											text: "{" + sPrefix + "caption}"
										})
									},
									change: fnNotify
								}).addStyleClass("sapUiTinyMarginEnd sapUiTinyMarginBottom"),
								new sap.m.Select({
									width: "13rem",
									selectedKey: "{" + sPrefix + "comparator}",
									items: {
										path: sPrefix + "/comparators",
										templateShareable: false,
										template: new sap.ui.core.Item({
											key: "{" + sPrefix + "key}",
											text: "{" + sPrefix + "text}",
											tooltip: "{" + sPrefix + "hint}"
										})
									},
									change: function (oEvent) {
										var oSelected = oEvent.getSource().getSelectedItem();
										oEvent.getSource().setTooltip(oSelected ? oSelected.getTooltip() : "");
										fnNotify(oEvent);
									}
								}).addStyleClass("sapUiTinyMarginEnd sapUiTinyMarginBottom"),
								new sap.m.Input({
									width: "14rem",
									value: "{" + sPrefix + "value}",
									visible: "{= ${" + sPrefix + "comparator} !== '..' }",
									change: fnNotify
								}).addStyleClass("sapUiTinyMarginEnd sapUiTinyMarginBottom"),
								new sap.m.Input({
									width: "7rem",
									value: "{" + sPrefix + "value_from}",
									visible: "{= ${" + sPrefix + "comparator} === '..' }",
									change: fnNotify
								}).addStyleClass("sapUiTinyMarginEnd sapUiTinyMarginBottom"),
								new sap.m.Text({
									text: "..",
									visible: "{= ${" + sPrefix + "comparator} === '..' }"
								}).addStyleClass("sapUiTinyMarginEnd sapUiTinyMarginBottom"),
								new sap.m.Input({
									width: "7rem",
									value: "{" + sPrefix + "value_to}",
									visible: "{= ${" + sPrefix + "comparator} === '..' }",
									change: fnNotify
								}).addStyleClass("sapUiTinyMarginEnd sapUiTinyMarginBottom"),
								new sap.m.Button({
									icon: "sap-icon://filter",
									type: sap.m.ButtonType.Transparent,
									enabled: false,
									tooltip: oPanel.getHeaderFilterTooltip(),
									visible: "{" + sPrefix + "linked_to_header}"
								}).addStyleClass("exf-p13n-advanced-search-action"),
								new sap.m.Button({
									icon: "sap-icon://delete",
									type: sap.m.ButtonType.Transparent,
									press: function (oEvent) {
										var oContext = oEvent.getSource().getBindingContext(sModel);
										if (oContext) {
											oPanel.removeCondition(parseInt(oContext.getPath().split("/").pop(), 10));
										}
									}
								}).addStyleClass("exf-p13n-advanced-search-action"),
								new sap.m.Button({
									icon: "sap-icon://add",
									type: sap.m.ButtonType.Transparent,
									press: function (oEvent) {
										var oContext = oEvent.getSource().getBindingContext(sModel);
										if (oContext) {
											oPanel.addCondition({ exclude: bExclude }, false, parseInt(oContext.getPath().split("/").pop(), 10) + 1);
										}
									}
								}).addStyleClass("exf-p13n-advanced-search-action")
							]
						}).addStyleClass("exf-p13n-advanced-search-row")
						})
					}
				});
			};
			return new sap.m.VBox({
				items: [
					new sap.m.Panel({
						headerText: this.getIncludeTitle(),
						expandable: true,
						expanded: true,
						content: [fnCreateList(false)]
					}),
					new sap.m.Panel({
						headerText: this.getExcludeTitle(),
						expandable: true,
						expanded: true,
						content: [fnCreateList(true)]
					})
			]
			});
		},

		_normalizeCondition: function (oCondition) {
			var oNormalized = Object.assign({
				expression: "",
				comparator: "=",
				value: "",
				value_from: "",
				value_to: "",
				exclude: false,
				linked_to_header: false
			}, oCondition || {});
			var mLegacy = {
				Contains: "=", EQ: "==", LT: "<", LE: "<=", GT: ">", GE: ">="
			};
			if (mLegacy[oNormalized.comparator]) {
				oNormalized.comparator = mLegacy[oNormalized.comparator];
			}
			oNormalized.exclude = oNormalized.exclude === true || oNormalized.exclude === "true" || oNormalized.exclude === 1;
			if (!oNormalized.expression && oNormalized.attribute_alias) {
				oNormalized.expression = oNormalized.attribute_alias;
			}
			if (oNormalized.comparator === ".." && oNormalized.value && !oNormalized.value_from && !oNormalized.value_to) {
				var iSeparator = String(oNormalized.value).indexOf("..");
				if (iSeparator > -1) {
					oNormalized.value_from = String(oNormalized.value).slice(0, iSeparator);
					oNormalized.value_to = String(oNormalized.value).slice(iSeparator + 2);
				}
			}
			return oNormalized;
		},

		_getConditionsReference: function () {
			var oModel = this.getModel(this.getModelName());
			return oModel ? (oModel.getProperty("/advanced_search") || []) : [];
		},

		getConditions: function () {
			return this._getConditionsReference();
		},

		hasConditionValue: function (oCondition) {
			if (!oCondition) {
				return false;
			}
			if (oCondition.comparator === "..") {
				return oCondition.value_from !== "" && oCondition.value_from !== null && oCondition.value_from !== undefined
					|| oCondition.value_to !== "" && oCondition.value_to !== null && oCondition.value_to !== undefined;
			}
			return oCondition.value !== "" && oCondition.value !== null && oCondition.value !== undefined;
		},

		_ensureGroupRows: function () {
			var aConditions = this._getConditionsReference();
			var bHasInclude = aConditions.some(function (oCondition) { return oCondition.exclude !== true; });
			var bHasExclude = aConditions.some(function (oCondition) { return oCondition.exclude === true; });
			if (!bHasInclude) {
				this.addCondition({ exclude: false }, true, 0);
			}
			if (!bHasExclude) {
				this.addCondition({ exclude: true }, true);
			}
		},

		setConditions: function (aConditions) {
			var oModel = this.getModel(this.getModelName());
			var oPanel = this;
			var aNormalized = (aConditions || []).map(function (oCondition) {
				return oPanel._normalizeCondition(oCondition);
			});
			if (oModel) {
				oModel.setProperty("/advanced_search", aNormalized);
				aNormalized.forEach(function (oCondition) {
					if (oCondition.linked_to_header === true) {
						oPanel._syncHeaderFilter(oCondition, false);
					}
				});
			}
			this._ensureGroupRows();
			this.fireConditionChange();
		},

		addCondition: function (oCondition, bSuppressEvent, iIndex) {
			var oModel = this.getModel(this.getModelName());
			var aConditions = this._getConditionsReference().slice();
			var aSearchables = oModel ? (oModel.getProperty("/searchables") || []) : [];
			var oNormalized = this._normalizeCondition(oCondition || {
				expression: aSearchables.length ? aSearchables[0].attribute_alias : ""
			});
			if (typeof iIndex === "number") {
				aConditions.splice(iIndex, 0, oNormalized);
			} else {
				aConditions.push(oNormalized);
			}
			if (oModel) {
				oModel.setProperty("/advanced_search", aConditions);
			}
			if (bSuppressEvent !== true) {
				this.fireConditionChange();
			}
		},

		upsertHeaderCondition: function (oCondition) {
			var oModel = this.getModel(this.getModelName());
			var aConditions = this._getConditionsReference().slice();
			var oNormalized = this._normalizeCondition(Object.assign({}, oCondition, { exclude: false, linked_to_header: true }));
			var iExisting = aConditions.findIndex(function (oItem) {
				return oItem.linked_to_header === true && oItem.expression === oNormalized.expression;
			});
			if (iExisting === -1) {
				iExisting = aConditions.findIndex(function (oItem) {
					return oItem.exclude !== true && oItem.linked_to_header !== true
						&& !oItem.value && !oItem.value_from && !oItem.value_to;
				});
			}
			if (iExisting > -1) {
				aConditions.splice(iExisting, 1, oNormalized);
			} else {
				aConditions.push(oNormalized);
			}
			if (oModel) {
				oModel.setProperty("/advanced_search", aConditions);
			}
			this._ensureGroupRows();
			this.fireConditionChange();
		},

		removeHeaderCondition: function (sExpression) {
			var oModel = this.getModel(this.getModelName());
			var aConditions = this._getConditionsReference().filter(function (oCondition) {
				return oCondition.linked_to_header !== true || oCondition.expression !== sExpression;
			});
			if (oModel) {
				oModel.setProperty("/advanced_search", aConditions);
			}
			this._ensureGroupRows();
			this.fireConditionChange();
		},

		removeCondition: function (iIndex) {
			var oModel = this.getModel(this.getModelName());
			var aConditions = this._getConditionsReference().slice();
			var oRemoved = aConditions[iIndex];
			if (!oRemoved) {
				return;
			}
			if (oRemoved.linked_to_header === true) {
				this._syncHeaderFilter(oRemoved, true);
			}
			var iGroupCount = aConditions.filter(function (oCondition) {
				return oCondition.exclude === oRemoved.exclude;
			}).length;
			if (iGroupCount === 1) {
				var aSearchables = oModel ? (oModel.getProperty("/searchables") || []) : [];
				aConditions.splice(iIndex, 1, this._normalizeCondition({
					expression: aSearchables.length ? aSearchables[0].attribute_alias : "",
					exclude: oRemoved.exclude
				}));
			} else {
				aConditions.splice(iIndex, 1);
			}
			if (oModel) {
				oModel.setProperty("/advanced_search", aConditions);
			}
			this.fireConditionChange();
		},

		removeConditionsByExpression: function (sExpression) {
			var oModel = this.getModel(this.getModelName());
			var oPanel = this;
			var aRemaining = [];
			this._getConditionsReference().forEach(function (oCondition) {
				if (oCondition.expression === sExpression) {
					if (oCondition.linked_to_header === true) {
						oPanel._syncHeaderFilter(oCondition, true);
					}
				} else {
					aRemaining.push(oCondition);
				}
			});
			if (oModel) {
				oModel.setProperty("/advanced_search", aRemaining);
			}
			this._ensureGroupRows();
			this.fireConditionChange();
		},

		removeAllConditions: function () {
			var oPanel = this;
			this._getConditionsReference().forEach(function (oCondition) {
				if (oCondition.linked_to_header === true) {
					oPanel._syncHeaderFilter(oCondition, true);
				}
			});
			this.setConditions([]);
		},

		_syncHeaderFilter: function (oCondition, bRemove) {
			var oTable = sap.ui.getCore().byId(this.getDataTableId());
			if (!oTable || typeof oTable.getColumns !== "function") {
				return;
			}
			var sValue = "";
			var bHasValue = !bRemove && this.hasConditionValue(oCondition);
			if (bHasValue) {
				sValue = oCondition.comparator === ".."
					? String(oCondition.value_from || "") + ".." + String(oCondition.value_to || "")
					: String(oCondition.comparator || "=") + String(oCondition.value || "");
			}
			oTable.getColumns().forEach(function (oColumn) {
				if (oColumn.getFilterProperty && oColumn.getFilterProperty() === oCondition.expression) {
					oColumn.setFilterValue(sValue);
					oColumn.setFiltered(bHasValue);
				}
			});
		}
	});
})();