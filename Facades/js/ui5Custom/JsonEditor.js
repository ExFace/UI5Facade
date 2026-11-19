sap.ui.define([
    "sap/ui/core/Control",
    "sap/ui/core/library",
    "sap/ui/core/Popup",
    "libs/exface/json/jsoneditor",
    "libs/exface/json/picoModal",
    "libs/exface/json/exfJsonEditor"
], function (Control, coreLibrary, Popup, JSONEditor, picoModal, oJsonEditorModule) {
    "use strict";

    var oJsonEditor = oJsonEditorModule || window.exfJsonEditor;

    /**
     * UI5 host for exfJsonEditor. A native value property supports two-way and
     * relative bindings without manually subscribing to a specific JSONModel.
     */
    return Control.extend("exface.ui5Custom.JsonEditor", {
        metadata: {
            properties: {
                value: {type: "string", defaultValue: ""},
                width: {type: "sap.ui.core.CSSSize", defaultValue: "100%"},
                height: {type: "sap.ui.core.CSSSize", defaultValue: "300px"},
                enabled: {type: "boolean", defaultValue: true},
                required: {type: "boolean", defaultValue: false},
                valueState: {type: "sap.ui.core.ValueState", defaultValue: coreLibrary.ValueState.None},
                valueStateText: {type: "string", defaultValue: ""},
                editorConfig: {type: "object", defaultValue: null}
            },
            events: {
                change: {parameters: {value: {type: "string"}}}
            }
        },

        renderer: {
            apiVersion: 2,
            render: function (oRm, oControl) {
                oRm.openStart("div", oControl);
                oRm.class("exf-ui5-jsoneditor");
                oRm.style("width", oControl.getWidth());
                oRm.style("height", oControl.getHeight());
                oRm.style("position", "relative");
                oRm.openEnd();
                oRm.openStart("div", oControl.getId() + "-editor");
                oRm.style("height", "100%");
                oRm.style("width", "100%");
                oRm.openEnd();
                oRm.close("div");
                oRm.close("div");
            }
        },

        onBeforeRendering: function () {
            if (this._oEditor) {
                this._sEditorMode = this._oEditor.getEditor().getMode();
            }
            this._destroyEditor();
        },

        onAfterRendering: function () {
            var oControl = this;
            var oConfig = Object.assign({}, this.getEditorConfig(), {
                JSONEditor: JSONEditor || window.JSONEditor,
                picoModal: picoModal || window.picoModal,
                disabled: !this.getEnabled(),
                required: this.getRequired(),
                modalParent: sap.ui.getCore().getStaticAreaRef(),
                onModal: function (domModal, domOverlay) {
                    domModal.setAttribute("data-sap-ui-integration-popup-content", "");
                    var iZIndex = Popup.getNextZIndex();
                    domOverlay.style.zIndex = iZIndex;
                    domModal.style.zIndex = iZIndex + 1;
                },
                onChange: function (sValue) {
                    // Suppress invalidation, not binding propagation. UI5 writes the
                    // property back using the binding's mode and binding context.
                    oControl.setProperty("value", sValue, true);
                    oControl.fireChange({value: sValue});
                }
            });
            this._oEditor = oJsonEditor.create(this.getDomRef("editor"), oConfig, this.getProperty("value"));
            var oShell = window.exfLauncher && window.exfLauncher.getShell();
            if (oShell && oShell.getModel()) {
                this._oOnlineBinding = oShell.getModel().bindProperty("/_network/online");
                this._oOnlineBinding.attachChange(function () {
                    oControl._oEditor.refreshServerAvailability();
                });
            }
            if (this._sEditorMode === "code" && this._oEditor.getEditor().getMode() !== "code") {
                this._oEditor.getEditor().setMode("code");
            }
            this._applyValueState();
        },

        exit: function () {
            this._destroyEditor();
        },

        _destroyEditor: function () {
            if (this._oOnlineBinding) {
                this._oOnlineBinding.destroy();
                this._oOnlineBinding = null;
            }
            if (this._oEditor) {
                this._oEditor.destroy();
                this._oEditor = null;
            }
        },

        setValue: function (mValue) {
            var sValue = oJsonEditor.emptyValue(oJsonEditor.normalizeValue(mValue));
            this.setProperty("value", sValue, true);
            if (this._oEditor) {
                this._oEditor.setValue(sValue);
            }
            return this;
        },

        setEnabled: function (bValue) {
            this.setProperty("enabled", bValue, true);
            if (this._oEditor) {
                this._oEditor.setDisabled(!bValue);
            }
            return this;
        },

        setRequired: function (bValue) {
            this.setProperty("required", bValue, true);
            if (this._oEditor) {
                this._oEditor.setRequired(bValue);
            }
            return this;
        },

        setValueState: function (sValue) {
            this.setProperty("valueState", sValue, true);
            this._applyValueState();
            return this;
        },

        setValueStateText: function (sValue) {
            this.setProperty("valueStateText", sValue, true);
            this._applyValueState();
            return this;
        },

        _applyValueState: function () {
            var domRoot = this.getDomRef();
            if (!domRoot) {
                return;
            }
            var bError = this.getValueState() === coreLibrary.ValueState.Error;
            domRoot.style.outline = bError ? "2px solid var(--sapField_InvalidColor, #bb0000)" : "";
            domRoot.style.outlineOffset = "-2px";
            domRoot.setAttribute("aria-invalid", bError ? "true" : "false");
            domRoot.title = bError ? this.getValueStateText() : "";
        },

        getFocusDomRef: function () {
            var domEditor = this.getDomRef("editor");
            return domEditor && (domEditor.querySelector('[contenteditable="true"], textarea') || domEditor);
        }
    });
});
