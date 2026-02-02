/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["./BaseAction","sap/ui/integration/util/openCardDialog","sap/ui/core/Element","sap/ui/dom/jquery/Focusable"],function(e,t,a){"use strict";var r=e.extend("sap.ui.integration.cards.actions.ShowCardAction",{metadata:{library:"sap.ui.integration"}});r.prototype.execute=function(){var e=this.getParameters()||{},r=this.getCardInstance(),i=r.getHostInstance();if(i&&i.onShowCard){let t;if(e._cardId){t=a.getElementById(e._cardId)}else{t=r._createChildCard(e)}i.onShowCard(t,e);return}t(r,this.getParameters())};return r});
//# sourceMappingURL=ShowCardAction.js.map