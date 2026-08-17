sap.ui.define([
	"sap/ui/core/Control"
], function (Control) {
	"use strict";

	/**
	 * Renders an HTML template with value placeholders - optimized for use in table cells.
	 *
	 * ## Why not sap.ui.core.HTML?
	 *
	 * `sap.ui.core.HTML` takes an HTML string and replaces its entire DOM subtree on every
	 * rerendering. In a virtualized table, where rows are recycled on every scroll tick, this
	 * means parsing + sanitizing + recreating the whole markup of every visible cell over and
	 * over again, which produces huge spikes of temporary DOM nodes.
	 *
	 * This control avoids that completely:
	 *
	 * 1. The template markup is parsed exactly ONCE per template string (cached statically,
	 *    so all cells of a column share the same parsed structure).
	 * 2. Rendering uses the RenderManager "apiVersion 2" (semantic rendering), so UI5 patches
	 *    the existing DOM in place instead of replacing it. Only text that actually changed is
	 *    written to the DOM.
	 * 3. Values are passed as a single composite binding, so one model update means one
	 *    property change - not one per placeholder.
	 *
	 * ## Template syntax
	 *
	 * The `template` is regular HTML. Values are referenced by index using the slot syntax
	 * `[[exf-slot-0]]`, `[[exf-slot-1]]`, etc. Slots may appear in text content and in
	 * attribute values (e.g. `title`).
	 *
	 * ```
	 * new exface.ui5Custom.DisplayTemplate({
	 *     values: {
	 *         parts: [{path: 'STOCK'}, {path: 'PLANNED'}],
	 *         formatter: function() { return Array.prototype.slice.call(arguments); }
	 *     }
	 * }).setTemplate('<p>Stock <strong>[[exf-slot-0]]</strong> / Plan [[exf-slot-1]]</p>');
	 * ```
	 *
	 * Note: `setTemplate()` is deliberately called after the constructor, because UI5 would
	 * otherwise try to interpret curly braces inside the markup as binding syntax.
	 *
	 * ## Security
	 *
	 * Values are always written via `RenderManager.text()` and are therefore escaped - they can
	 * never inject markup. While parsing the template, `id` and `on*` attributes are dropped and
	 * `javascript:` URLs are ignored.
	 *
	 * @extends sap.ui.core.Control
	 * @alias exface.ui5Custom.DisplayTemplate
	 */

	var VOID_TAGS = {
		area: 1, base: 1, br: 1, col: 1, embed: 1, hr: 1, img: 1,
		input: 1, link: 1, meta: 1, param: 1, source: 1, track: 1, wbr: 1
	};

	var SLOT_PREFIX = "[[exf-slot-";
	var SLOT_REGEX = /\[\[exf-slot-(\d+)\]\]/g;

	// Parsed templates are shared by all instances - a table column has one template for all rows
	var mTemplateCache = {};

	/**
	 * Splits a string into a token list, where numbers are slot indexes.
	 * Returns the plain string if it does not contain any slots.
	 */
	function tokenize(sText) {
		if (sText.indexOf(SLOT_PREFIX) === -1) {
			return sText;
		}
		var aTokens = [];
		var iLast = 0;
		var oMatch;
		SLOT_REGEX.lastIndex = 0;
		while ((oMatch = SLOT_REGEX.exec(sText)) !== null) {
			if (oMatch.index > iLast) {
				aTokens.push(sText.substring(iLast, oMatch.index));
			}
			aTokens.push(parseInt(oMatch[1], 10));
			iLast = oMatch.index + oMatch[0].length;
		}
		if (iLast < sText.length) {
			aTokens.push(sText.substring(iLast));
		}
		return aTokens;
	}

	function resolve(vTokens, aValues) {
		if (typeof vTokens === "string") {
			return vTokens;
		}
		var sResult = "";
		for (var i = 0; i < vTokens.length; i++) {
			var vToken = vTokens[i];
			if (typeof vToken === "number") {
				var vValue = aValues[vToken];
				sResult += (vValue === undefined || vValue === null) ? "" : vValue;
			} else {
				sResult += vToken;
			}
		}
		return sResult;
	}

	function parseStyle(sStyle) {
		var aDecls = sStyle.split(";");
		var aStyles = [];
		for (var i = 0; i < aDecls.length; i++) {
			var sDecl = aDecls[i];
			var iColon = sDecl.indexOf(":");
			if (iColon === -1) {
				continue;
			}
			var sName = sDecl.substring(0, iColon).trim();
			var sValue = sDecl.substring(iColon + 1).trim();
			if (sName === "" || sValue === "") {
				continue;
			}
			aStyles.push({ name: sName, value: tokenize(sValue) });
		}
		return aStyles.length > 0 ? aStyles : null;
	}

	function parseElement(oEl) {
		var oNode = {
			tag: oEl.tagName.toLowerCase(),
			attrs: null,
			styles: null,
			classes: null,
			children: null
		};
		oNode.isVoid = VOID_TAGS[oNode.tag] === 1;

		var aAttrs = oEl.attributes;
		for (var i = 0; i < aAttrs.length; i++) {
			var sName = aAttrs[i].name.toLowerCase();
			var sValue = aAttrs[i].value;
			// Ids would be duplicated in every row and event handlers must not come from data
			if (sName === "id" || sName.indexOf("on") === 0) {
				continue;
			}
			if (sName === "class") {
				var aClasses = sValue.split(/\s+/);
				for (var j = 0; j < aClasses.length; j++) {
					if (aClasses[j] !== "") {
						(oNode.classes = oNode.classes || []).push(tokenize(aClasses[j]));
					}
				}
				continue;
			}
			if (sName === "style") {
				oNode.styles = parseStyle(sValue);
				continue;
			}
			if ((sName === "href" || sName === "src") && /^\s*javascript:/i.test(sValue)) {
				continue;
			}
			(oNode.attrs = oNode.attrs || []).push({ name: sName, value: tokenize(sValue) });
		}

		if (!oNode.isVoid) {
			oNode.children = parseNodes(oEl);
		}
		return oNode;
	}

	function parseNodes(oParent) {
		var aNodes = [];
		var aChildren = oParent.childNodes;
		for (var i = 0; i < aChildren.length; i++) {
			var oChild = aChildren[i];
			switch (oChild.nodeType) {
				case 3: // text
					var sText = oChild.nodeValue;
					// Skip indentation of pretty-printed templates, but keep real spaces between inline elements
					if (sText === "" || (/^\s+$/.test(sText) && /[\r\n]/.test(sText))) {
						continue;
					}
					aNodes.push({ text: tokenize(sText) });
					break;
				case 1: // element
					aNodes.push(parseElement(oChild));
					break;
			}
		}
		return aNodes;
	}

	function parseTemplate(sHtml) {
		var oDoc = new DOMParser().parseFromString("<body>" + sHtml + "</body>", "text/html");
		return parseNodes(oDoc.body);
	}

	function renderAttributes(oRm, oNode, aValues) {
		var i;
		if (oNode.attrs) {
			for (i = 0; i < oNode.attrs.length; i++) {
				oRm.attr(oNode.attrs[i].name, resolve(oNode.attrs[i].value, aValues));
			}
		}
		if (oNode.classes) {
			for (i = 0; i < oNode.classes.length; i++) {
				oRm.class(resolve(oNode.classes[i], aValues));
			}
		}
		if (oNode.styles) {
			for (i = 0; i < oNode.styles.length; i++) {
				oRm.style(oNode.styles[i].name, resolve(oNode.styles[i].value, aValues));
			}
		}
	}

	function renderNodes(oRm, aNodes, aValues) {
		for (var i = 0; i < aNodes.length; i++) {
			var oNode = aNodes[i];
			if (oNode.text !== undefined) {
				oRm.text(resolve(oNode.text, aValues));
			} else if (oNode.isVoid) {
				oRm.voidStart(oNode.tag);
				renderAttributes(oRm, oNode, aValues);
				oRm.voidEnd();
			} else {
				oRm.openStart(oNode.tag);
				renderAttributes(oRm, oNode, aValues);
				oRm.openEnd();
				renderNodes(oRm, oNode.children, aValues);
				oRm.close(oNode.tag);
			}
		}
	}

	return Control.extend("exface.ui5Custom.DisplayTemplate", {
		metadata: {
			properties: {
				/**
				 * HTML markup with `[[exf-slot-N]]` placeholders. Set it via `setTemplate()`,
				 * not via the constructor - see the class description.
				 */
				template: { type: "string", group: "Misc", defaultValue: "" },
				/**
				 * Array of values for the slots of the template - normally a composite binding.
				 */
				values: { type: "object", group: "Data", defaultValue: null }
			}
		},

		renderer: {
			apiVersion: 2,
			render: function (oRm, oControl) {
				oRm.openStart("div", oControl);
				oRm.class("exfDisplayTemplate");
				oRm.openEnd();
				renderNodes(oRm, oControl._getTemplateNodes(), oControl.getValues() || []);
				oRm.close("div");
			}
		},

		/**
		 * Only invalidate if a value really changed - scrolling a table reapplies identical
		 * values very often and every one of them would cause a rerendering.
		 */
		setValues: function (aValues) {
			var aOld = this.getProperty("values");
			if (aOld && aValues && aOld.length === aValues.length) {
				var bEqual = true;
				for (var i = 0; i < aValues.length; i++) {
					if (aOld[i] !== aValues[i]) {
						bEqual = false;
						break;
					}
				}
				if (bEqual) {
					return this;
				}
			}
			return this.setProperty("values", aValues);
		},

		_getTemplateNodes: function () {
			var sTemplate = this.getTemplate() || "";
			var aNodes = mTemplateCache[sTemplate];
			if (aNodes === undefined) {
				aNodes = mTemplateCache[sTemplate] = parseTemplate(sTemplate);
			}
			return aNodes;
		}
	});
});
