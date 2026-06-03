/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([],function(){"use strict";const n={};n.waitTransition=function(n,t){if(typeof t!=="function"){throw new Error("fnCallback should be a function")}return new Promise(function(e){n.addEventListener("transitionend",e,{once:true});let i;const o=function(n){i||=n;if(n!==i){t()}else{window.requestAnimationFrame(o)}};window.requestAnimationFrame(o)})};return n});
//# sourceMappingURL=Animation.js.map