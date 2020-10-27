/*!
 * OpenUI5
 * (c) Copyright 2009-2020 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["../_AnnotationHelperBasics","sap/base/Log","sap/ui/base/BindingParser","sap/ui/base/ManagedObject","sap/ui/base/SyncPromise","sap/ui/performance/Measurement"],function(B,L,a,M,S,b){'use strict';var A="sap.ui.model.odata.v4.AnnotationHelper",p=[A],P=A+"/getExpression",E,r=/^\{@i18n>[^\\\{\}:]+\}$/,o={And:"&&",Eq:"===",Ge:">=",Gt:">",Le:"<=",Lt:"<",Ne:"!==",Not:"!",Or:"||"},s=false,t={"Edm.Boolean":"boolean","Edm.Byte":"number","Edm.Date":"Date","Edm.DateTimeOffset":"DateTimeOffset","Edm.Decimal":"Decimal","Edm.Double":"number","Edm.Guid":"string","Edm.Int16":"number","Edm.Int32":"number","Edm.Int64":"Decimal","Edm.SByte":"number","Edm.Single":"number","Edm.String":"string","Edm.TimeOfDay":"TimeOfDay"},T={Bool:"Edm.Boolean",Float:"Edm.Double",Date:"Edm.Date",DateTimeOffset:"Edm.DateTimeOffset",Decimal:"Edm.Decimal",Guid:"Edm.Guid",Int:"Edm.Int64",Int32:"Edm.Int32",String:"Edm.String",TimeOfDay:"Edm.TimeOfDay"},m={"boolean":false,"Date":false,"DateTimeOffset":true,"Decimal":true,"number":false,"string":false,"TimeOfDay":false};function c(e,f){return S.resolve().then(function(){d(e,f);});}function d(e,f){B.error(e,f,A);}E={adjustOperands:function(O,e){if(O.result!=="constant"&&O.category==="number"&&e.result==="constant"&&e.type==="Edm.Int64"){e.category="number";}if(O.result!=="constant"&&O.category==="Decimal"&&e.result==="constant"&&e.type==="Edm.Int32"){e.category="Decimal";e.type=O.type;}},apply:function(e,f){var F=B.descend(e,"$Function","string");switch(F.value){case"odata.concat":return E.concat(f);case"odata.fillUriTemplate":return E.fillUriTemplate(f);case"odata.uriEncode":return E.uriEncode(f);default:return c(F,"unknown function: "+F.value);}},concat:function(e){var f;B.expectType(e,"array");f=e.value.map(function(u,i){return E.parameter(e,i);});return S.all(f).then(function(g){var h,i,R;h=e.asExpression||g.some(function(j){return j.result==="expression";});i=g.filter(function(j){return j.type!=='edm:Null';}).map(function(j){if(h){E.wrapExpression(j);}return B.resultToString(j,h,e.complexBinding);});R=h?{result:"expression",value:i.join("+")}:{result:"composite",value:i.join("")};R.type="Edm.String";return R;});},conditional:function(e){var C=e.complexBinding,f=C?Object.assign({},e,{complexBinding:false}):e;function g(h,i){return B.resultToString(E.wrapExpression(h),true,i);}return S.all([E.parameter(f,0,"Edm.Boolean"),E.parameter(e,1),E.parameter(e,2)]).then(function(R){var h=R[0],i=R[1],j=R[2],k=i.type;if(i.type==="edm:Null"){k=j.type;}else if(j.type!=="edm:Null"&&i.type!==j.type){d(e,"Expected same type for second and third parameter, types are '"+i.type+"' and '"+j.type+"'");}return{result:"expression",type:k,value:g(h,false)+"?"+g(i,C)+":"+g(j,C)};});},constant:function(e,f){var v=e.value;if(f==="String"){if(r.test(v)){return{ignoreTypeInPath:true,result:"binding",type:"Edm.String",value:v.slice(1,-1)};}}return{result:"constant",type:T[f],value:v};},expression:function(e){var R=e.value,f=e,g;if(R===null){g="Null";}else if(typeof R==="boolean"){g="Bool";}else if(typeof R==="number"){g=isFinite(R)&&Math.floor(R)===R?"Int32":"Float";}else if(typeof R==="string"){g="String";}else{B.expectType(e,"object");if(R.$kind==="Property"){e.value=e.model.getObject(e.path+"@sapui.name");return E.path(e);}["$And","$Apply","$Date","$DateTimeOffset","$Decimal","$Float","$Eq","$Ge","$Gt","$Guid","$If","$Int","$Le","$Lt","$Name","$Ne","$Not","$Null","$Or","$Path","$PropertyPath","$TimeOfDay","$LabeledElement"].forEach(function(h){if(R.hasOwnProperty(h)){g=h.slice(1);f=B.descend(e,h);}});}switch(g){case"Apply":return E.apply(e,f);case"If":return E.conditional(f);case"Name":case"Path":case"PropertyPath":return E.path(f);case"Date":case"DateTimeOffset":case"Decimal":case"Guid":case"Int":case"String":case"TimeOfDay":B.expectType(f,"string");case"Bool":case"Float":case"Int32":return S.resolve(E.constant(f,g));case"And":case"Eq":case"Ge":case"Gt":case"Le":case"Lt":case"Ne":case"Or":return E.operator(f,g);case"Not":return E.not(f);case"Null":return S.resolve({result:"constant",type:"edm:Null",value:null});default:return c(e,"Unsupported OData expression");}},fetchCurrencyOrUnit:function(e,v,f,C){var g="sap.ui.model.odata.type.Unit",h="@@requestUnitsOfMeasure",i=e.model,j=e.path+"@Org.OData.Measures.V1.Unit/$Path",k=i.getObject(j);function l(n,q,u){return B.resultToString({constraints:n,result:"binding",type:q,value:e.prefix+u},false,true);}if(!k){g="sap.ui.model.odata.type.Currency";h="@@requestCurrencyCodes";j=e.path+"@Org.OData.Measures.V1.ISOCurrency/$Path";k=i.getObject(j);}if(!k){return undefined;}return i.fetchObject(j+"/$").then(function(n){return{result:"composite",type:g,value:(t[f]==="number"?"{formatOptions:{parseAsString:false},":"{")+"mode:'TwoWay',parts:["+l(C,f,v)+","+l(i.getConstraints(n,j),n.$Type,k)+",{mode:'OneTime',path:'/##"+h+"',targetType:'any'}"+"],type:'"+g+"'}"};});},fillUriTemplate:function(e){var i,f=[],g;e.complexBinding=false;g=[E.parameter(e,0,"Edm.String")];for(i=1;i<e.value.length;i+=1){f[i]=B.descend(e,i,"object");g.push(E.expression(B.descend(f[i],"$LabeledElement",true)));}return S.all(g).then(function(R){var n,h=[],j="";h.push('odata.fillUriTemplate(',B.resultToString(R[0],true,false),',{');for(i=1;i<e.value.length;i+=1){n=B.property(f[i],"$Name","string");h.push(j,B.toJSON(n),":",B.resultToString(R[i],true,false));j=",";}h.push("})");return{result:"expression",type:"Edm.String",value:h.join("")};});},formatOperand:function(R,w){if(R.result==="constant"){switch(R.category){case"boolean":case"number":return String(R.value);}}if(w){E.wrapExpression(R);}return B.resultToString(R,true,false);},getExpression:function(f){if(f.value===undefined){return undefined;}b.average(P,"",p);if(!s&&M.bindingParser===a.simpleParser){L.warning("Complex binding syntax not active",null,A);s=true;}return E.expression(f).then(function(R){return B.resultToString(R,false,f.complexBinding);},function(e){if(e instanceof SyntaxError){return"Unsupported: "+a.complexParser.escape(B.toErrorString(f.value));}throw e;}).finally(function(){b.end(P);}).unwrap();},not:function(e){e.asExpression=true;e.complexBinding=false;return E.expression(e).then(function(f){return{result:"expression",type:"Edm.Boolean",value:"!"+B.resultToString(E.wrapExpression(f),true,false)};});},operator:function(e,f){var g=f==="And"||f==="Or"?"Edm.Boolean":undefined;e.complexBinding=false;return S.all([E.parameter(e,0,g),E.parameter(e,1,g)]).then(function(R){var n,h=R[0],i=R[1],j="",v,V;if(h.type!=="edm:Null"&&i.type!=="edm:Null"){h.category=t[h.type];i.category=t[i.type];E.adjustOperands(h,i);E.adjustOperands(i,h);if(h.category!==i.category){d(e,"Expected two comparable parameters but instead saw "+h.type+" and "+i.type);}switch(h.category){case"Decimal":j=",'Decimal'";break;case"DateTimeOffset":j=",'DateTime'";break;}n=m[h.category];}v=E.formatOperand(h,!n);V=E.formatOperand(i,!n);return{result:"expression",type:"Edm.Boolean",value:n?"odata.compare("+v+","+V+j+")"+o[f]+"0":v+o[f]+V};});},parameter:function(e,i,f){var g=B.descend(e,i,true);return E.expression(g).then(function(R){if(f&&f!==R.type){d(g,"Expected "+f+" but instead saw "+R.type);}return R;});},path:function(e){var i=e.ignoreAsPrefix,f=e.model,g,v=e.value;if(i&&v.startsWith(i)){v=v.slice(i.length);}B.expectType(e,"string");g=f.fetchObject(e.path+"/$");if(g.isPending()&&!e.$$valueAsPromise){g.caught();g=S.resolve();}return g.then(function(h){var C,j,k=h&&h.$Type;if(h&&e.complexBinding){C=f.getConstraints(h,e.path);j=E.fetchCurrencyOrUnit(e,v,k,C);}return j||{constraints:C,formatOptions:k==="Edm.String"&&!(e.formatOptions&&"parseKeepsEmptyString"in e.formatOptions)?Object.assign({parseKeepsEmptyString:true},e.formatOptions):e.formatOptions,parameters:e.parameters,result:"binding",type:k,value:e.prefix+v};});},uriEncode:function(e){return E.parameter(e,0).then(function(R){return{result:"expression",type:"Edm.String",value:R.type==="Edm.String"?'odata.uriEncode('+B.resultToString(R,true,false)+","+B.toJSON(R.type)+")":'String('+B.resultToString(R,true,false)+")"};});},wrapExpression:function(R){if(R.result==="expression"){R.value="("+R.value+")";}return R;}};return E;},false);
