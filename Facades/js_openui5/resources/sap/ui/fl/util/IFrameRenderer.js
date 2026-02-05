/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/base/util/uid"],function(t){"use strict";function e(t,e,n){if(n!==""||n.toLowerCase()==="auto"){t.style(e,n)}}function n(t){return Object.keys(t).filter(e=>t[e]).map(t=>t.replace(/[A-Z]/g,"-$&").toLowerCase()).join(" ")}var i={apiVersion:2};i.render=function(i,o){i.openStart("div",o);e(i,"width",o.getWidth());e(i,"height",o.getHeight());i.openEnd();i.openStart("iframe",`${o.getId()}-${t()}`);i.style("width","100%");i.style("height","100%");i.style("display","block");i.style("border","none");const r=o.getTitle();const s=o.getAdvancedSettings();const{additionalSandboxParameters:a,...d}=s;const c=a?.join(" ");const l=n(d);const u=c?`${l} ${c}`:l;i.attr("src",o.getUrl());if(r){i.attr("title",r)}i.attr("sandbox",u);i.openEnd();i.close("iframe");i.close("div")};return i},true);
//# sourceMappingURL=IFrameRenderer.js.map