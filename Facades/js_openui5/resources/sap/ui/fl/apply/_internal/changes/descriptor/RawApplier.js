/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/base/util/isEmptyObject"],function(t){"use strict";const s={applyChanges(s,e,n,r){s.forEach(function(s,c){try{const a=n[c];e=s.applyChange(e,a);if(!s.skipPostprocessing&&!t(a.getTexts())){e=r.processTexts(e,a.getTexts())}}catch(t){r.handleError(t)}});return e}};return s});
//# sourceMappingURL=RawApplier.js.map