/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/mvc/Controller"],function(e){"use strict";return e.extend("sap.ui.fl.support.diagnostics.Flexibility",{onDownloadPress(){const e=this.getView().getModel("flexToolSettings").getProperty("/anonymizeData");const t=this.getView().getViewData().plugin;t.sendGetDataEvent(e)}})});
//# sourceMappingURL=Flexibility.controller.js.map