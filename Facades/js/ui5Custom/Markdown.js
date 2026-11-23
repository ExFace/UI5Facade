sap.ui.define([
    "sap/ui/core/Control",
    "sap/ui/core/library",
    "sap/ui/core/Popup",
    "libs/exface/markian/bridge"
], function (Control, coreLibrary, Popup) {
    "use strict";

    /**
     * Native value bindings avoid stale documents and manual JSONModel listeners.
     * Markian owns editing/rendering; this control owns UI5 state and disposal.
     */
    return Control.extend("exface.ui5Custom.Markdown", {
        metadata: {
            properties: {
                value: {type: "string", defaultValue: ""},
                width: {type: "sap.ui.core.CSSSize", defaultValue: "100%"},
                height: {type: "sap.ui.core.CSSSize", defaultValue: "300px"},
                enabled: {type: "boolean", defaultValue: true},
                required: {type: "boolean", defaultValue: false},
                valueState: {type: "sap.ui.core.ValueState", defaultValue: coreLibrary.ValueState.None},
                valueStateText: {type: "string", defaultValue: ""},
                viewer: {type: "boolean", defaultValue: false},
                editorConfig: {type: "object", defaultValue: null}
            },
            events: {
                change: {parameters: {value: {type: "string"}}}
            }
        },

        renderer: {
            apiVersion: 2,
            /**
             * Keeps a stable control root outside the library-owned mount.
             */
            render: function (oRm, oControl) {
                oRm.openStart("div", oControl);
                oRm.class("exf-ui5-markdown");
                oRm.style("width", oControl.getWidth());
                oRm.style("height", oControl.getHeight());
                oRm.openEnd();
                oRm.openStart("div", oControl.getId() + "-editor");
                oRm.style("height", "100%");
                oRm.openEnd();
                oRm.close("div");
                oRm.close("div");
            }
        },

        /**
         * Retains complete drafts, including one-way-bound edits, before UI5 replaces the DOM.
         */
        onBeforeRendering: function () {
            if (this._oEditor) this._sDraft = this._oEditor.get();
            this._destroyEditor();
        },

        /**
         * Recreates only this instance and routes user changes through native binding modes.
         */
        onAfterRendering: function () {
            var oControl = this;
            var oConfig = this.getEditorConfig() || {};
            var oOptions = Object.assign({}, oConfig.options, {
                markdown: this._sDraft === undefined ? this.getProperty("value") : this._sDraft,
                disabled: !this.getEnabled()
            });
            var domHost = this.getDomRef("editor");
            domHost.addEventListener("change", function (oEvent) {
                if (oEvent.target !== domHost || !oControl._oEditor) return;
                var sValue = oControl._oEditor.get();
                oControl.setProperty("value", sValue, true);
                oControl.fireChange({value: sValue});
            });
            if (!this.getViewer()) {
                oOptions.onFullscreenEnter = this._enterFullscreen.bind(this);
            }
            this._oEditor = window.MarkianAdapter.mount(domHost, oOptions, this.getViewer(), oConfig.mermaidSource, oConfig.allowImages);
            if (!this.getViewer()) {
                this._oEditor.instance.element.classList.add("exf-ui5-markian");
                this._oEditor.instance.element.parentElement.style.height = "100%";
                this._oEditor.instance.element.style.minHeight = "0";
            }
            this._applyValueState();
        },

        /**
         * Destruction must also restore a fullscreen editor before removing its UI5 parent.
         */
        exit: function () {
            this._destroyEditor();
        },

        /**
         * Disposes observers, asynchronous renders and library-owned overlays together.
         */
        _destroyEditor: function () {
            if (this._oEditor) {
                this._oEditor.destroy();
                this._oEditor = null;
            }
        },

        /**
         * Exposes the active draft instead of a potentially older bound property.
         */
        getValue: function () {
            return this._oEditor ? this._oEditor.get() : this._sDraft === undefined ? this.getProperty("value") : this._sDraft;
        },

        /**
         * Host loads update bindings but never masquerade as user changes.
         */
        setValue: function (mValue) {
            var sValue = String(mValue == null ? "" : mValue);
            this._sDraft = sValue;
            this.setProperty("value", sValue, true);
            if (this._oEditor) this._oEditor.set(sValue);
            return this;
        },

        /**
         * Permission changes must not discard pending source or history.
         */
        setEnabled: function (bValue) {
            this.setProperty("enabled", bValue, true);
            if (this._oEditor) this._oEditor.setDisabled(!this.getEnabled());
            return this;
        },

        /**
         * Required state belongs to UI5 validation rather than document editing.
         */
        setRequired: function (bValue) {
            this.setProperty("required", bValue, true);
            this._applyValueState();
            return this;
        },

        /**
         * Validation must not invalidate and rebuild the editor.
         */
        setValueState: function (sValue) {
            this.setProperty("valueState", sValue, true);
            this._applyValueState();
            return this;
        },

        /**
         * Keeps the visible tooltip synchronized with the current validation message.
         */
        setValueStateText: function (sValue) {
            this.setProperty("valueStateText", sValue, true);
            this._applyValueState();
            return this;
        },

        /**
         * Value-bound tooltips change on every keystroke. Preserve UI5's tooltip
         * ownership and delegates, but update the DOM instead of rebuilding Markian.
         */
        setAggregation: function (sName, vValue, bSuppressInvalidate) {
            var oResult = Control.prototype.setAggregation.call(this, sName, vValue, sName === "tooltip" || bSuppressInvalidate);
            if (sName === "tooltip") this._applyValueState();
            return oResult;
        },

        /**
         * Uses the stable host for styling and accessibility, not private editor DOM.
         */
        _applyValueState: function () {
            var domRoot = this.getDomRef();
            if (!domRoot) return;
            var bError = this.getValueState() === coreLibrary.ValueState.Error;
            domRoot.style.outline = bError ? "2px solid var(--sapField_InvalidColor, #bb0000)" : "";
            domRoot.style.outlineOffset = "-2px";
            domRoot.setAttribute("aria-invalid", bError ? "true" : "false");
            domRoot.setAttribute("aria-required", this.getRequired() ? "true" : "false");
            domRoot.title = bError ? this.getValueStateText() : this.getTooltip_AsString() || "";
            if (this._oEditor && !this.getViewer()) {
                this._oEditor.instance.element.setAttribute("aria-invalid", bError ? "true" : "false");
            }
        },

        /**
         * Delegates focus to the active Markian view, including source editing.
         */
        focus: function () {
            if (this._oEditor) this._oEditor.focus();
        },

        /**
         * Reparents outside modal clipping while registering the editor with UI5's focus trap.
         * The restore function is called by Markian on exit, cancellation and destruction.
         */
        _enterFullscreen: function (oContext) {
            var domEditor = oContext.element;
            var bAddCompactClass = !domEditor.classList.contains("sapUiSizeCompact") && !!domEditor.closest(".sapUiSizeCompact");
            if (bAddCompactClass) domEditor.classList.add("sapUiSizeCompact");
            var domPlaceholder = document.createComment("Markian fullscreen");
            var oViewport = window.visualViewport;
            domEditor.before(domPlaceholder);
            domEditor.setAttribute("data-sap-ui-integration-popup-content", "");
            var sZIndex = domEditor.style.zIndex;
            domEditor.style.zIndex = Popup.getNextZIndex();
            sap.ui.getCore().getStaticAreaRef().appendChild(domEditor);
            domEditor.classList.add("markian-fullscreen");
            var fnResize = function () {
                domEditor.style.setProperty("--markian-viewport-top", (oViewport ? oViewport.offsetTop : 0) + "px");
                domEditor.style.setProperty("--markian-viewport-left", (oViewport ? oViewport.offsetLeft : 0) + "px");
                domEditor.style.setProperty("--markian-viewport-width", (oViewport ? oViewport.width : window.innerWidth) + "px");
                domEditor.style.setProperty("--markian-viewport-height", (oViewport ? oViewport.height : window.innerHeight) + "px");
                oContext.instance.resize();
            };
            if (oViewport) {
                oViewport.addEventListener("resize", fnResize);
                oViewport.addEventListener("scroll", fnResize);
            }
            window.addEventListener("resize", fnResize);
            fnResize();
            return function () {
                if (oViewport) {
                    oViewport.removeEventListener("resize", fnResize);
                    oViewport.removeEventListener("scroll", fnResize);
                }
                window.removeEventListener("resize", fnResize);
                domPlaceholder.replaceWith(domEditor);
                domEditor.classList.remove("markian-fullscreen");
                if (bAddCompactClass) domEditor.classList.remove("sapUiSizeCompact");
                domEditor.removeAttribute("data-sap-ui-integration-popup-content");
                domEditor.style.zIndex = sZIndex;
                ["top", "left", "width", "height"].forEach(function (sName) {
                    domEditor.style.removeProperty("--markian-viewport-" + sName);
                });
            };
        }
    });
});
