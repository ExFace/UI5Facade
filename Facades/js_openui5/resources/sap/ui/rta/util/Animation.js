/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([],function(){"use strict";var n={};n.waitTransition=function(n,e){if(typeof e!=="function"){throw new Error("fnCallback should be a function")}return new Promise(function(i){n.addEventListener("transitionend",i,{once:true});var t;var r=function(n){t||=n;if(n!==t){e()}else{window.requestAnimationFrame(r)}};window.requestAnimationFrame(r)})};return n});
//# sourceMappingURL=Animation.js.map