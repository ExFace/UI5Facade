/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/apply/_internal/changeHandlers/ChangeHandlerRegistration","sap/ui/fl/apply/_internal/flexState/communication/FLPAboutInfo","sap/ui/fl/apply/_internal/DelegateMediator","sap/ui/fl/changeHandler/ChangeAnnotation"],function(e,a,t,l){"use strict";e.registerPredefinedChangeHandlers();e.getChangeHandlersOfLoadedLibsAndRegisterOnNewLoadedLibs();e.registerAnnotationChangeHandler({changeHandler:l,isDefaultChangeHandler:true});t.registerReadDelegate({modelType:"sap.ui.model.odata.v4.ODataModel",delegate:"sap/ui/fl/write/_internal/delegates/ODataV4ReadDelegate"});t.registerReadDelegate({modelType:"sap.ui.model.odata.v2.ODataModel",delegate:"sap/ui/fl/write/_internal/delegates/ODataV2ReadDelegate"});t.registerReadDelegate({modelType:"sap.ui.model.odata.ODataModel",delegate:"sap/ui/fl/write/_internal/delegates/ODataV2ReadDelegate"});a.initialize()});
//# sourceMappingURL=init.js.map