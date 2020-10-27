/*!
 * OpenUI5
 * (c) Copyright 2009-2020 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(['sap/ui/model/BindingMode','sap/ui/model/Model','./ResourcePropertyBinding',"sap/base/i18n/ResourceBundle","sap/base/Log"],function(B,M,R,a,L){"use strict";var r=/^(?:\/|\.)*/;var b=M.extend("sap.ui.model.resource.ResourceModel",{constructor:function(d){var u;M.apply(this,arguments);this.aCustomBundles=[];this.bReenhance=false;this.bAsync=!!(d&&d.async);this.sDefaultBindingMode=d.defaultBindingMode||B.OneWay;this.mSupportedBindingModes={"OneWay":true,"TwoWay":false,"OneTime":!this.bAsync};if(this.bAsync&&this.sDefaultBindingMode==B.OneTime){L.warning("Using binding mode OneTime for asynchronous ResourceModel is not supported!");}this.oData=Object.assign({},d);u=Array.isArray(this.oData.enhanceWith)&&this.oData.enhanceWith.some(function(e){return e instanceof a;});if(d&&d.bundle){this._oResourceBundle=d.bundle;u=true;}else if(d&&(d.bundleUrl||d.bundleName)){if(u){delete this.oData.enhanceWith;if(d.terminologies||d.activeTerminologies){throw new Error("'terminologies' parameter and 'activeTerminologies' parameter are not"+" supported in configuration when enhanceWith contains ResourceBundles");}}_(this);}else{throw new Error("At least bundle, bundleName or bundleUrl must be provided!");}if(u&&Array.isArray(d.enhanceWith)){if(this.bAsync){this._pEnhanced=d.enhanceWith.reduce(function(c,e){return c.then(this.enhance.bind(this,e));}.bind(this),Promise.resolve());}else{d.enhanceWith.forEach(this.enhance.bind(this));}}}});b._sanitizeBundleName=function(s){if(s&&(s[0]==="/"||s[0]===".")){L.error('Incorrect resource bundle name "'+s+'"','Leading slashes or dots in resource bundle names are ignored, since such names are'+' invalid UI5 module names. Please check whether the resource bundle "'+s+'" is actually needed by your application.',"sap.base.i18n.ResourceBundle");s=s.replace(r,"");}return s;};b.loadResourceBundle=function(d,A){var c=sap.ui.getCore().getConfiguration(),l=d.bundleLocale,p;if(!l){l=c.getLanguage();}d.bundleName=b._sanitizeBundleName(d.bundleName);p=Object.assign({async:A,includeInfo:c.getOriginInfo(),locale:l},d);return a.create(p);};b.prototype.enhance=function(d){var t=this,f,p=this.bAsync?new Promise(function(e){f=e;}):null;function c(){if(d instanceof a){t._oResourceBundle._enhance(d);t.checkUpdate(true);if(p){f(true);}}else{if(d.terminologies){throw new Error("'terminologies' parameter is not"+" supported for enhancement");}var e=b.loadResourceBundle(d,t.bAsync);if(e instanceof Promise){e.then(function(g){t._oResourceBundle._enhance(g);t.checkUpdate(true);f(true);},function(){f(true);});}else if(e){t._oResourceBundle._enhance(e);t.checkUpdate(true);}}}if(this._oPromise){Promise.resolve(this._oPromise).then(c);}else{c();}if(!this.bReenhance){this.aCustomBundles.push(d);}return p;};b.prototype.bindProperty=function(p){return new R(this,p);};b.prototype.getProperty=function(p){return this._oResourceBundle?this._oResourceBundle.getText(p):null;};b.prototype.getResourceBundle=function(){if(!this.bAsync){return this._oResourceBundle;}else{var p=this._oPromise;if(p){return new Promise(function(c,d){function e(o){c(o);}p.then(e,e);});}else{return Promise.resolve(this._oResourceBundle);}}};b.prototype._handleLocalizationChange=function(){_(this);};b.prototype._reenhance=function(){this.bReenhance=true;this.aCustomBundles.forEach(function(d){this.enhance(d);}.bind(this));this.bReenhance=false;};function _(m){var d=m.oData;if(d&&(d.bundleUrl||d.bundleName)){var c=b.loadResourceBundle(d,d.async);if(c instanceof Promise){var e={url:a._getUrl(d.bundleUrl,b._sanitizeBundleName(d.bundleName)),async:true};m.fireRequestSent(e);m._oPromise=c;m._oPromise.then(function(o){m._oResourceBundle=o;m._reenhance();delete m._oPromise;m.checkUpdate(true);m.fireRequestCompleted(e);});}else{m._oResourceBundle=c;m._reenhance();m.checkUpdate(true);}}}return b;});
