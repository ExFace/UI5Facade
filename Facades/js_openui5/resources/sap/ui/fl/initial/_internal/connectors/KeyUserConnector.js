/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/base/util/merge","sap/ui/fl/initial/_internal/connectors/BackendConnector","sap/ui/fl/Layer"],function(e,a,n){"use strict";var t="/flex/keyuser";var r="/v2";var s=e({},a,{layers:[n.CUSTOMER,n.PUBLIC],API_VERSION:r,ROUTES:{DATA:`${t+r}/data/`,SETTINGS:`${t+r}/settings`},isLanguageInfoRequired:true,loadFeatures(e){return a.loadFeatures.call(this,e)},loadFlexData(e){e.cacheable=true;return a.sendRequest.call(this,e).then(function(e){e.contents.map(function(e,a,n){n[a].changes=(e.changes||[]).concat(e.compVariants)});e.contents[0].cacheKey=e.cacheKey;return e.contents})}});return s});
//# sourceMappingURL=KeyUserConnector.js.map