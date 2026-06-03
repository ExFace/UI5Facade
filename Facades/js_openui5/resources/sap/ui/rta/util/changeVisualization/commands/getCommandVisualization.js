/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/util/changeVisualization/commands/RenameVisualization","sap/ui/rta/util/changeVisualization/commands/MoveVisualization","sap/ui/rta/util/changeVisualization/commands/CombineVisualization","sap/ui/rta/util/changeVisualization/commands/SplitVisualization","sap/ui/rta/util/changeVisualization/commands/CreateContainerVisualization"],function(i,a,n,t,e){"use strict";const s={rename:i,move:a,combine:n,split:t,createContainer:e};return function(i){let a=i.commandName;if(a==="settings"){a=i.changeCategory}return s[a]}});
//# sourceMappingURL=getCommandVisualization.js.map