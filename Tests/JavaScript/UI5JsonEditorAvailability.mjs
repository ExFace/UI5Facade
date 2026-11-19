/**
 * Run in JsonEditor.html to check forced-offline updates and UI5 binding cleanup.
 */
export async function runUI5JsonEditorAvailabilityRegression() {
    await new Promise(fnResolve => sap.ui.getCore().attachInit(fnResolve));
    const [JsonEditor, JSONModel] = await new Promise((fnResolve, fnReject) => {
        sap.ui.require(['exface/ui5Custom/JsonEditor', 'sap/ui/model/json/JSONModel'],
            (...aModules) => fnResolve(aModules), fnReject);
    });
    const oLauncherDescriptor = Object.getOwnPropertyDescriptor(window, 'exfLauncher');
    const oNetwork = new JSONModel({_network: {online: true}});
    let bCompatible = true;
    let oControl;
    function check(bValue, sMessage) {
        if (!bValue) throw new Error(sMessage);
    }
    try {
        Object.defineProperty(window, 'exfLauncher', {configurable: true, value: {
            isOnline: () => oNetwork.getProperty('/_network/online'),
            getShell: () => ({getModel: () => oNetwork})
        }});
        oControl = new JsonEditor('ui5-availability', {value: '{"value":"NAME"}'}).setEditorConfig({
            uxon: true, autosuggest: true, validate: false,
            translations: {SERVER_UNAVAILABLE: 'Autosuggest is not available.'},
            schema: () => 'widget', rootPrototype: () => '', rootObject: () => '',
            serverAvailable: () => bCompatible
        });
        oControl.placeAt('fixtures');
        sap.ui.getCore().applyChanges();
        const fnBadge = () => oControl.getDomRef().querySelector('.exf-jsoneditor-unavailable');
        check(fnBadge().hidden, 'Compatible online UI5 editor must not show a warning');
        oNetwork.setProperty('/_network/online', false);
        check(!fnBadge().hidden, 'Forced-offline model changes must update the warning without user interaction');
        const oEditor = oControl.getDomRef('editor').exfJsonEditor.getEditor();
        check((await oEditor.options.autocomplete.getOptions('', [], 'value', oEditor)).length === 0,
            'Forced-offline UI5 must not request suggestions');
        oControl.setValue('{"edited":true}');
        check(oControl.getValue() === '{"edited":true}', 'Forced-offline UI5 must remain editable');
        bCompatible = false;
        oNetwork.setProperty('/_network/online', true);
        check(!fnBadge().hidden, 'Online state must not enable an unsupported backend');
        bCompatible = true;
        oNetwork.setProperty('/_network/online', false);
        oNetwork.setProperty('/_network/online', true);
        check(fnBadge().hidden, 'Compatible backend must recover on reconnect');
        oControl.invalidate();
        sap.ui.getCore().applyChanges();
        oNetwork.setProperty('/_network/online', false);
        check(!fnBadge().hidden && oControl.getDomRef().querySelectorAll('.exf-jsoneditor-unavailable').length === 1,
            'Rerendering must replace the network binding and warning');
        oControl.destroy();
        oControl = null;
        oNetwork.setProperty('/_network/online', true);
        return ['UI5 forced-offline binding updates and compatible-backend gating', 'UI5 network-binding cleanup on rerender and destruction'];
    } finally {
        if (oControl) oControl.destroy();
        oNetwork.destroy();
        if (oLauncherDescriptor) Object.defineProperty(window, 'exfLauncher', oLauncherDescriptor);
        else delete window.exfLauncher;
    }
}
