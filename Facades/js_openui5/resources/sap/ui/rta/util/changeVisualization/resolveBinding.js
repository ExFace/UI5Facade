/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/base/BindingInfo","sap/ui/base/ManagedObject","sap/ui/fl/Utils"],function(e,t,n){"use strict";var i=t.extend("sap.ui.rta.util.changeVisualization.HelperControl",{metadata:{library:"sap.ui.rta",properties:{resolved:{type:"any"}}}});return function(t,o){const s=n.getViewForControl(o);const r=s&&s.getController();if(!n.isBinding(t,r)){return undefined}const a=typeof t==="string"?e.parse(t,r):{...t};if(!a){return undefined}const d=new i;const l=a.parts||[a];l.forEach(function(e){const t=e.model;if(t){d.setModel(o.getModel(t),t);d.setBindingContext(o.getBindingContext(t),t)}else{d.setModel(o.getModel());d.setBindingContext(o.getBindingContext())}});d.bindProperty("resolved",a);const u=d.getResolved();d.destroy();return u}});
//# sourceMappingURL=resolveBinding.js.map