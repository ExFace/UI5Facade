ace.define("ace/mode/plsql_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,t,r){
/* ***** BEGIN LICENSE BLOCK *****
 * Distributed under the BSD license:
 *
 * Copyright (c) 2012, Ajax.org B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of Ajax.org B.V. nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL AJAX.ORG B.V. BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * ***** END LICENSE BLOCK ***** */
"use strict";var i=e("../lib/oop");var n=e("./text_highlight_rules").TextHighlightRules;var o=function(){var e="all|alter|and|any|array|arrow|as|asc|at|begin|between|by|case|check|clusters|cluster|colauth|columns|compress|connect|crash|create|cross|current|database|declare|default|delete|desc|distinct|drop|else|end|exception|exclusive|exists|fetch|form|for|foreign|from|goto|grant|group|having|identified|if|in|inner|indexes|index|insert|intersect|into|is|join|key|left|like|lock|minus|mode|natural|nocompress|not|nowait|null|of|on|option|or|order,overlaps|outer|primary|prior|procedure|public|range|record|references|resource|revoke|right|select|share|size|sql|start|subtype|tabauth|table|then|to|type|union|unique|update|use|values|view|views|when|where|with";var t="true|false";var r="abs|acos|add_months|ascii|asciistr|asin|atan|atan2|avg|bfilename|bin_to_num|bitand|cardinality|case|cast|ceil|chartorowid|chr|coalesce|compose|concat|convert|corr|cos|cosh|count|covar_pop|covar_samp|cume_dist|current_date|current_timestamp|dbtimezone|decode|decompose|dense_rank|dump|empty_blob|empty_clob|exp|extract|first_value|floor|from_tz|greatest|group_id|hextoraw|initcap|instr|instr2|instr4|instrb|instrc|lag|last_day|last_value|lead|least|length|length2|length4|lengthb|lengthc|listagg|ln|lnnvl|localtimestamp|log|lower|lpad|ltrim|max|median|min|mod|months_between|nanvl|nchr|new_time|next_day|nth_value|nullif|numtodsinterval|numtoyminterval|nvl|nvl2|power|rank|rawtohex|regexp_count|regexp_instr|regexp_replace|regexp_substr|remainder|replace|round|rownum|rpad|rtrim|sessiontimezone|sign|sin|sinh|soundex|sqrt|stddev|substr|sum|sys_context|sysdate|systimestamp|tan|tanh|to_char|to_clob|to_date|to_dsinterval|to_lob|to_multi_byte|to_nclob|to_number|to_single_byte|to_timestamp|to_timestamp_tz|to_yminterval|translate|trim|trunc|tz_offset|uid|upper|user|userenv|var_pop|var_samp|variance|vsize";var i="char|nchar|nvarchar2|varchar2|long|raw|"+"number|numeric|float|dec|decimal|integer|int|smallint|real|double|precision|"+"date|timestamp|interval|year|day|"+"bfile|blob|clob|nclob|"+"rowid|urowid";var n=this.createKeywordMapper({"support.function":r,keyword:e,"constant.language":t,"storage.type":i},"identifier",true);this.$rules={start:[{token:"comment",regex:"--.*$"},{token:"comment",start:"/\\*",end:"\\*/"},{token:"string",regex:'".*?"'},{token:"string",regex:"'.*?'"},{token:"constant.numeric",regex:"[+-]?\\d+(?:(?:\\.\\d*)?(?:[eE][+-]?\\d+)?)?\\b"},{token:n,regex:"[a-zA-Z_$][a-zA-Z0-9_$]*\\b"},{token:"keyword.operator",regex:"\\+|\\-|\\/|\\/\\/|%|<@>|@>|<@|&|\\^|~|<|>|<=|=>|==|!=|<>|="},{token:"paren.lparen",regex:"[\\(]"},{token:"paren.rparen",regex:"[\\)]"},{token:"text",regex:"\\s+"}]};this.normalizeRules()};i.inherits(o,n);t.plsqlHighlightRules=o});ace.define("ace/mode/folding/cstyle",["require","exports","module","ace/lib/oop","ace/range","ace/mode/folding/fold_mode"],function(e,t,r){"use strict";var i=e("../../lib/oop");var n=e("../../range").Range;var o=e("./fold_mode").FoldMode;var a=t.FoldMode=function(e){if(e){this.foldingStartMarker=new RegExp(this.foldingStartMarker.source.replace(/\|[^|]*?$/,"|"+e.start));this.foldingStopMarker=new RegExp(this.foldingStopMarker.source.replace(/\|[^|]*?$/,"|"+e.end))}};i.inherits(a,o);(function(){this.foldingStartMarker=/([\{\[\(])[^\}\]\)]*$|^\s*(\/\*)/;this.foldingStopMarker=/^[^\[\{\(]*([\}\]\)])|^[\s\*]*(\*\/)/;this.singleLineBlockCommentRe=/^\s*(\/\*).*\*\/\s*$/;this.tripleStarBlockCommentRe=/^\s*(\/\*\*\*).*\*\/\s*$/;this.startRegionRe=/^\s*(\/\*|\/\/)#?region\b/;this._getFoldWidgetBase=this.getFoldWidget;this.getFoldWidget=function(e,t,r){var i=e.getLine(r);if(this.singleLineBlockCommentRe.test(i)){if(!this.startRegionRe.test(i)&&!this.tripleStarBlockCommentRe.test(i))return""}var n=this._getFoldWidgetBase(e,t,r);if(!n&&this.startRegionRe.test(i))return"start";return n};this.getFoldWidgetRange=function(e,t,r,i){var n=e.getLine(r);if(this.startRegionRe.test(n))return this.getCommentRegionBlock(e,n,r);var o=n.match(this.foldingStartMarker);if(o){var a=o.index;if(o[1])return this.openingBracketBlock(e,o[1],r,a);var s=e.getCommentFoldRange(r,a+o[0].length,1);if(s&&!s.isMultiLine()){if(i){s=this.getSectionRange(e,r)}else if(t!="all")s=null}return s}if(t==="markbegin")return;var o=n.match(this.foldingStopMarker);if(o){var a=o.index+o[0].length;if(o[1])return this.closingBracketBlock(e,o[1],r,a);return e.getCommentFoldRange(r,a,-1)}};this.getSectionRange=function(e,t){var r=e.getLine(t);var i=r.search(/\S/);var o=t;var a=r.length;t=t+1;var s=t;var l=e.getLength();while(++t<l){r=e.getLine(t);var c=r.search(/\S/);if(c===-1)continue;if(i>c)break;var d=this.getFoldWidgetRange(e,"all",t);if(d){if(d.start.row<=o){break}else if(d.isMultiLine()){t=d.end.row}else if(i==c){break}}s=t}return new n(o,a,s,e.getLine(s).length)};this.getCommentRegionBlock=function(e,t,r){var i=t.search(/\s*$/);var o=e.getLength();var a=r;var s=/^\s*(?:\/\*|\/\/|--)#?(end)?region\b/;var l=1;while(++r<o){t=e.getLine(r);var c=s.exec(t);if(!c)continue;if(c[1])l--;else l++;if(!l)break}var d=r;if(d>a){return new n(a,i,d,t.length)}}}).call(a.prototype)});ace.define("ace/mode/folding/sql",["require","exports","module","ace/lib/oop","ace/mode/folding/cstyle"],function(e,t,r){"use strict";var i=e("../../lib/oop");var n=e("./cstyle").FoldMode;var o=t.FoldMode=function(){};i.inherits(o,n);(function(){}).call(o.prototype)});ace.define("ace/mode/plsql",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/plsql_highlight_rules","ace/mode/folding/sql"],function(e,t,r){
/* ***** BEGIN LICENSE BLOCK *****
 * Distributed under the BSD license:
 *
 * Copyright (c) 2012, Ajax.org B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of Ajax.org B.V. nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL AJAX.ORG B.V. BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * ***** END LICENSE BLOCK ***** */
"use strict";var i=e("../lib/oop");var n=e("./text").Mode;var o=e("./plsql_highlight_rules").plsqlHighlightRules;var a=e("./folding/sql").FoldMode;var s=function(){this.HighlightRules=o;this.foldingRules=new a};i.inherits(s,n);(function(){this.lineCommentStart="--";this.blockComment={start:"/*",end:"*/"};this.$id="ace/mode/plsql"}).call(s.prototype);t.Mode=s});(function(){ace.require(["ace/mode/plsql"],function(e){if(typeof module=="object"&&typeof exports=="object"&&module){module.exports=e}})})();
//# sourceMappingURL=mode-plsql.js.map