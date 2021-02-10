/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
var __createBinding=this&&this.__createBinding||(Object.create?function(e,t,s,i){void 0===i&&(i=s),Object.defineProperty(e,i,{enumerable:!0,get:function(){return t[s]}})}:function(e,t,s,i){void 0===i&&(i=s),e[i]=t[s]}),__setModuleDefault=this&&this.__setModuleDefault||(Object.create?function(e,t){Object.defineProperty(e,"default",{enumerable:!0,value:t})}:function(e,t){e.default=t}),__importStar=this&&this.__importStar||function(e){if(e&&e.__esModule)return e;var t={};if(null!=e)for(var s in e)"default"!==s&&Object.prototype.hasOwnProperty.call(e,s)&&__createBinding(t,e,s);return __setModuleDefault(t,e),t},__importDefault=this&&this.__importDefault||function(e){return e&&e.__esModule?e:{default:e}};define(["require","exports","d3-selection","d3-dispatch","TYPO3/CMS/Core/Ajax/AjaxRequest","./Notification","./Enum/KeyTypes","./Icons","./Tooltip","./Enum/IconTypes"],(function(e,t,s,i,n,o,r,a,d,l){"use strict";Object.defineProperty(t,"__esModule",{value:!0}),t.SvgTree=void 0,s=__importStar(s),i=__importStar(i),n=__importDefault(n);t.SvgTree=class{constructor(){this.dispatch=null,this.isOverSvg=!1,this.svg=null,this.container=null,this.nodesContainer=null,this.nodesBgContainer=null,this.hoveredNode=null,this.nodes=[],this.settings={showCheckboxes:!1,showIcons:!1,marginTop:15,nodeHeight:20,indentWidth:16,width:300,duration:400,dataUrl:"",validation:{maxItems:Number.MAX_VALUE},defaultProperties:{},unselectableElements:[],expandUpToLevel:null,readOnlyMode:!1,exclusiveNodesIdentifiers:""},this.textPosition=0,this.d3wrapper=null,this.iconsContainer=null,this.linksContainer=null,this.data=new class{},this.wrapper=null,this.viewportHeight=0,this.scrollTop=0,this.scrollBottom=0,this.exclusiveSelectedNode=null,this.networkErrorTitle=TYPO3.lang.pagetree_networkErrorTitle,this.networkErrorMessage=TYPO3.lang.pagetree_networkErrorDesc}initialize(e,t){return!e.dataset.svgTreeInitialized&&(Object.assign(this.settings,t),this.wrapper=e,this.dispatch=i.dispatch("updateNodes","updateSvg","loadDataAfter","prepareLoadedNode","nodeSelectedAfter","nodeRightClick"),this.d3wrapper=s.select(this.wrapper),this.svg=this.d3wrapper.append("svg").attr("version","1.1").attr("width","100%").on("mouseover",()=>this.isOverSvg=!0).on("mouseout",()=>this.isOverSvg=!1).on("keydown",e=>this.handleKeyboardInteraction(e)),this.container=this.svg.append("g").attr("class","nodes-wrapper").attr("transform","translate("+this.settings.indentWidth/2+","+this.settings.nodeHeight/2+")"),this.nodesBgContainer=this.container.append("g").attr("class","nodes-bg"),this.linksContainer=this.container.append("g").attr("class","links"),this.nodesContainer=this.container.append("g").attr("class","nodes").attr("role","tree"),this.settings.showIcons&&(this.iconsContainer=this.svg.append("defs")),this.updateScrollPosition(),this.loadData(),this.wrapper.addEventListener("resize",()=>{this.updateScrollPosition(),this.update()}),this.wrapper.addEventListener("scroll",()=>{this.updateScrollPosition(),this.update()}),this.wrapper.addEventListener("svg-tree:visible",()=>{this.updateScrollPosition(),this.update()}),this.wrapper.svgtree=this,this.wrapper.dataset.svgTreeInitialized="true",this.wrapper.dispatchEvent(new Event("svg-tree:initialized")),this.resize(),!0)}resize(){window.addEventListener("resize",()=>{this.wrapper.getClientRects().length>0&&(this.updateScrollPosition(),this.update())})}switchFocus(e){if(null===e)return;e.parentNode.querySelectorAll("[tabindex]").forEach(e=>{e.setAttribute("tabindex","-1")}),e.setAttribute("tabindex","0"),e.focus()}switchFocusNode(e){this.switchFocus(this.getNodeElement(e))}getNodeElement(e){return document.getElementById("identifier-"+this.getNodeStateIdentifier(e))}updateScrollPosition(){this.viewportHeight=this.wrapper.getBoundingClientRect().height,this.scrollTop=this.wrapper.scrollTop,this.scrollBottom=this.scrollTop+this.viewportHeight+this.viewportHeight/2,d.hide(this.wrapper.querySelectorAll("[data-bs-toggle=tooltip]"))}loadData(){this.nodesAddPlaceholder(),new n.default(this.settings.dataUrl).get({cache:"no-cache"}).then(e=>e.resolve()).then(e=>{const t=Array.isArray(e)?e:[];this.replaceData(t),this.nodesRemovePlaceholder(),this.updateScrollPosition(),this.update()}).catch(e=>{throw this.errorNotification(e,!1),this.nodesRemovePlaceholder(),e})}replaceData(e){this.setParametersNode(e),this.dispatch.call("loadDataAfter",this),this.prepareDataForVisibleNodes(),this.nodesContainer.selectAll(".node").remove(),this.nodesBgContainer.selectAll(".node-bg").remove(),this.linksContainer.selectAll(".link").remove(),this.update()}setParametersNode(e=null){1===(e=(e=e||this.nodes).map((t,s)=>{if(void 0===t.command&&(t=Object.assign({},this.settings.defaultProperties,t)),t.expanded=null!==this.settings.expandUpToLevel?t.depth<this.settings.expandUpToLevel:Boolean(t.expanded),t.parents=[],t.parentsStateIdentifier=[],t.depth>0){let i=t.depth;for(let n=s;n>=0;n--){let s=e[n];s.depth<i&&(t.parents.push(n),t.parentsStateIdentifier.push(e[n].stateIdentifier),i=s.depth)}}if(t.canToggle=t.hasChildren,!t.stateIdentifier){const e=t.parents.length?t.parents[t.parents.length-1]:t.identifier;t.stateIdentifier=e+"_"+t.identifier}return void 0===t.checked&&(t.checked=!1),!1===t.selectable&&this.settings.unselectableElements.push(t.identifier),this.dispatch.call("prepareLoadedNode",this,t),t})).filter(e=>0===e.depth).length&&(e[0].expanded=!0,e[0].canToggle=!1),this.nodes=e}nodesRemovePlaceholder(){const e=this.svg.node().closest(".svg-tree"),t=null==e?void 0:e.querySelector(".node-loader");t&&(t.style.display="none");const s=null==e?void 0:e.querySelector(".svg-tree-loader");s&&(s.style.display="none")}nodesAddPlaceholder(e=null){const t=this.svg.node().closest(".svg-tree");if(e){const s=null==t?void 0:t.querySelector(".node-loader");s&&(s.style.top=""+(e.y+this.settings.marginTop),s.style.display="block")}else{const e=null==t?void 0:t.querySelector(".svg-tree-loader");e&&(e.style.display="block")}}hideChildren(e){e.expanded=!1,this.setExpandedState(e)}showChildren(e){e.expanded=!0,this.setExpandedState(e)}setExpandedState(e){const t=this.getNodeElement(e);t&&(e.hasChildren?t.setAttribute("aria-expanded",e.expanded?"true":"false"):t.removeAttribute("aria-expanded"))}refreshTree(){this.loadData()}expandAll(){this.nodes.forEach(this.showChildren.bind(this)),this.prepareDataForVisibleNodes(),this.update()}collapseAll(){this.nodes.forEach(this.hideChildren.bind(this)),this.prepareDataForVisibleNodes(),this.update()}prepareDataForVisibleNodes(){const e={};this.nodes.forEach((t,s)=>{t.expanded||(e[s]=!0)}),this.data.nodes=this.nodes.filter(t=>!0!==t.hidden&&!t.parents.some(t=>Boolean(e[t]))),this.data.links=[];let t=0;this.data.nodes.forEach((e,s)=>{e.x=e.depth*this.settings.indentWidth,e.readableRootline&&(t+=this.settings.nodeHeight),e.y=s*this.settings.nodeHeight+t,void 0!==e.parents[0]&&this.data.links.push({source:this.nodes[e.parents[0]],target:e}),this.settings.showIcons&&(this.fetchIcon(e.icon),this.fetchIcon(e.overlayIcon),e.locked&&this.fetchIcon("warning-in-use"))}),this.svg.attr("height",this.data.nodes.length*this.settings.nodeHeight+this.settings.nodeHeight/2+t)}fetchIcon(e,t=!0){e&&(this.icons=this.icons||{},e in this.icons||(this.icons[e]={identifier:e,icon:""},a.getIcon(e,a.sizes.small,null,null,l.MarkupIdentifiers.inline).then(s=>{let i=s.match(/<svg[\s\S]*<\/svg>/i);i&&(this.icons[e].icon=i[0]),t&&this.update()})))}update(){const e=Math.ceil(this.viewportHeight/this.settings.nodeHeight+1),t=Math.floor(Math.max(this.scrollTop-2*this.settings.nodeHeight,0)/this.settings.nodeHeight),i=this.data.nodes.slice(t,t+e),n=this.wrapper.querySelector('[tabindex="0"]'),o=i.find(e=>e.checked);let r=this.nodesContainer.selectAll(".node").data(i,e=>e.stateIdentifier);const a=this.nodesBgContainer.selectAll(".node-bg").data(i,e=>e.stateIdentifier);r.exit().remove(),a.exit().remove();const d=this.updateNodeBgClass(a);d.attr("class",(e,t)=>this.getNodeBgClass(e,t,d)).attr("style",e=>e.backgroundColor?"fill: "+e.backgroundColor+";":""),this.updateLinks(),r=this.enterSvgElements(r),r.attr("tabindex",(e,t)=>{if(void 0!==o){if(o===e)return"0"}else if(null===n){if(0===t)return"0"}else if(s.select(n).datum()===e)return"0";return"-1"}).attr("transform",this.getNodeTransform).select(".node-name").text(this.getNodeLabel),r.select(".chevron").attr("transform",this.getChevronTransform).style("fill",this.getChevronColor).attr("class",this.getChevronClass),r.select(".toggle").attr("visibility",this.getToggleVisibility),this.settings.showIcons&&(r.select("use.node-icon").attr("xlink:href",this.getIconId),r.select("use.node-icon-overlay").attr("xlink:href",this.getIconOverlayId),r.select("use.node-icon-locked").attr("xlink:href",e=>"#icon-"+(e.locked?"warning-in-use":""))),this.dispatch.call("updateNodes",this,r)}updateNodeBgClass(e){return e.enter().append("rect").merge(e).attr("width","100%").attr("height",this.settings.nodeHeight).attr("data-state-id",this.getNodeStateIdentifier).attr("transform",this.getNodeBgTransform).on("mouseover",(e,t)=>this.onMouseOverNode(t)).on("mouseout",(e,t)=>this.onMouseOutOfNode(t)).on("click",(e,t)=>{this.selectNode(t),this.switchFocusNode(t)}).on("contextmenu",(e,t)=>{this.dispatch.call("nodeRightClick",this,t)})}getIconId(e){return"#icon-"+e.icon}getIconOverlayId(e){return"#icon-"+e.overlayIcon}selectNode(e){if(!this.isNodeSelectable(e))return;const t=e.checked;this.handleExclusiveNodeSelection(e),this.settings.validation&&this.settings.validation.maxItems&&!t&&this.getSelectedNodes().length>=this.settings.validation.maxItems||(e.checked=!t,this.dispatch.call("nodeSelectedAfter",this,e),this.update())}errorNotification(e=null,t=!1){if(Array.isArray(e))e.forEach(e=>{o.error(e.title,e.message)});else{let t=this.networkErrorTitle;e&&e.target&&(e.target.status||e.target.statusText)&&(t+=" - "+(e.target.status||"")+" "+(e.target.statusText||"")),o.error(t,this.networkErrorMessage)}t&&this.loadData()}isNodeSelectable(e){return!this.settings.readOnlyMode&&-1===this.settings.unselectableElements.indexOf(e.identifier)}getSelectedNodes(){return this.nodes.filter(e=>e.checked)}appendTextElement(e){return e.append("text").attr("dx",e=>this.textPosition+(e.locked?15:0)).attr("dy",5).attr("class","node-name").on("click",(e,t)=>this.selectNode(t))}nodesUpdate(e){return(e=e.enter().append("g").attr("class",this.getNodeClass).attr("id",e=>"identifier-"+e.stateIdentifier).attr("role","treeitem").attr("aria-owns",e=>e.hasChildren?"group-identifier-"+e.stateIdentifier:null).attr("aria-level",this.getNodeDepth).attr("aria-setsize",this.getNodeSetsize).attr("aria-posinset",this.getNodePositionInSet).attr("aria-expanded",e=>e.hasChildren?e.expanded:null).attr("transform",this.getNodeTransform).attr("data-state-id",this.getNodeStateIdentifier).attr("title",this.getNodeTitle).on("mouseover",(e,t)=>this.onMouseOverNode(t)).on("mouseout",(e,t)=>this.onMouseOutOfNode(t)).on("contextmenu",(e,t)=>{e.preventDefault(),this.dispatch.call("nodeRightClick",this,t)})).append("text").text(e=>e.readableRootline).attr("class","node-rootline").attr("dx",0).attr("dy",-15).attr("visibility",e=>e.readableRootline?"visible":"hidden"),e}getNodeIdentifier(e){return e.identifier}getNodeDepth(e){return e.depth}getNodeSetsize(e){return e.siblingsCount}getNodePositionInSet(e){return e.siblingsPosition}getNodeStateIdentifier(e){return e.stateIdentifier}getNodeLabel(e){return(e.prefix||"")+e.name+(e.suffix||"")}getNodeClass(e){return"node identifier-"+e.stateIdentifier}getNodeBgClass(e,t,s){let i="node-bg",n=null,o=null;return"object"==typeof s&&(n=s.data()[t-1],o=s.data()[t+1]),e.checked&&(i+=" node-selected"),(n&&e.depth>n.depth||!n)&&(e.firstChild=!0,i+=" node-first-child"),(o&&e.depth>o.depth||!o)&&(e.lastChild=!0,i+=" node-last-child"),e.class&&(i+=" "+e.class),i}getNodeTitle(e){return e.tip?e.tip:"uid="+e.identifier}getChevronTransform(e){return e.expanded?"translate(16,0) rotate(90)":" rotate(0)"}getChevronColor(e){return e.expanded?"#000":"#8e8e8e"}getToggleVisibility(e){return e.canToggle?"visible":"hidden"}getChevronClass(e){return"chevron "+(e.expanded?"expanded":"collapsed")}getLinkPath(e){const t=e.target.x,s=e.target.y,i=[];return i.push("M"+e.source.x+" "+e.source.y),i.push("V"+s),e.target.hasChildren?i.push("H"+(t-2)):i.push("H"+(t+this.settings.indentWidth/4-2)),i.join(" ")}getNodeTransform(e){return"translate("+(e.x||0)+","+(e.y||0)+")"}getNodeBgTransform(e){return"translate(-8, "+((e.y||0)-10)+")"}clickOnIcon(e){this.dispatch.call("nodeRightClick",this,e)}clickOnLabel(e){this.selectNode(e)}chevronClick(e){e.expanded?this.hideChildren(e):this.showChildren(e),this.prepareDataForVisibleNodes(),this.update()}handleExclusiveNodeSelection(e){const t=this.settings.exclusiveNodesIdentifiers.split(",");this.settings.exclusiveNodesIdentifiers.length&&!1===e.checked&&(t.indexOf(""+e.identifier)>-1?(this.nodes.forEach(e=>{!0===e.checked&&(e.checked=!1,this.dispatch.call("nodeSelectedAfter",this,e))}),this.exclusiveSelectedNode=e):-1===t.indexOf(""+e.identifier)&&this.exclusiveSelectedNode&&(this.exclusiveSelectedNode.checked=!1,this.dispatch.call("nodeSelectedAfter",this,this.exclusiveSelectedNode),this.exclusiveSelectedNode=null))}onMouseOverNode(e){e.isOver=!0,this.hoveredNode=e;let t=this.svg.select('.nodes-bg .node-bg[data-state-id="'+e.stateIdentifier+'"]');t.size()&&t.classed("node-over",!0).attr("rx","3").attr("ry","3")}onMouseOutOfNode(e){e.isOver=!1,this.hoveredNode=null;let t=this.svg.select('.nodes-bg .node-bg[data-state-id="'+e.stateIdentifier+'"]');t.size()&&t.classed("node-over node-alert",!1).attr("rx","0").attr("ry","0")}handleKeyboardInteraction(e){const t=e.target;let i=s.select(t).datum();if(-1===[r.KeyTypesEnum.ENTER,r.KeyTypesEnum.SPACE,r.KeyTypesEnum.END,r.KeyTypesEnum.HOME,r.KeyTypesEnum.LEFT,r.KeyTypesEnum.UP,r.KeyTypesEnum.RIGHT,r.KeyTypesEnum.DOWN].indexOf(e.keyCode))return;e.preventDefault();const n=t.parentNode;switch(e.keyCode){case r.KeyTypesEnum.END:this.scrollTop=this.wrapper.lastElementChild.getBoundingClientRect().height+this.settings.nodeHeight-this.viewportHeight,n.scrollIntoView({behavior:"smooth",block:"end"}),this.update(),this.switchFocus(n.lastElementChild);break;case r.KeyTypesEnum.HOME:this.scrollTop=this.nodes[0].y,this.wrapper.scrollTo({top:this.scrollTop,behavior:"smooth"}),this.update(),this.switchFocus(n.firstElementChild);break;case r.KeyTypesEnum.LEFT:if(i.expanded)i.canToggle&&(this.hideChildren(i),this.prepareDataForVisibleNodes(),this.update());else if(i.parents.length>0){let e=this.nodes[i.parents[0]];this.scrollNodeIntoVisibleArea(e,"up"),this.switchFocusNode(e)}break;case r.KeyTypesEnum.UP:this.scrollNodeIntoVisibleArea(i,"up"),this.switchFocus(t.previousSibling);break;case r.KeyTypesEnum.RIGHT:i.expanded?(this.scrollNodeIntoVisibleArea(i,"down"),this.switchFocus(t.nextSibling)):i.hasChildren&&(this.showChildren(i),this.prepareDataForVisibleNodes(),this.update(),this.switchFocus(t));break;case r.KeyTypesEnum.DOWN:this.scrollNodeIntoVisibleArea(i,"down"),this.switchFocus(t.nextSibling);break;case r.KeyTypesEnum.ENTER:case r.KeyTypesEnum.SPACE:this.selectNode(i)}}scrollNodeIntoVisibleArea(e,t="up"){if("up"===t&&this.scrollTop>e.y-this.settings.nodeHeight)this.scrollTop=e.y-this.settings.nodeHeight;else{if(!("down"===t&&this.scrollTop+this.viewportHeight<=e.y+3*this.settings.nodeHeight))return;this.scrollTop=this.scrollTop+this.settings.nodeHeight}this.wrapper.scrollTo({top:this.scrollTop,behavior:"smooth"}),this.update()}updateLinks(){const e=this.data.links.filter(e=>e.source.y<=this.scrollBottom&&e.target.y>=this.scrollTop-this.settings.nodeHeight).map(e=>(e.source.owns=e.source.owns||[],e.source.owns.push("identifier-"+e.target.stateIdentifier),e)),t=this.linksContainer.selectAll(".link").data(e);t.exit().remove(),t.enter().append("path").attr("class","link").attr("id",this.getGroupIdentifier).attr("role",e=>1===e.target.siblingsPosition&&e.source.owns.length>0?"group":null).attr("aria-owns",e=>1===e.target.siblingsPosition&&e.source.owns.length>0?e.source.owns.join(" "):null).merge(t).attr("d",e=>this.getLinkPath(e))}getGroupIdentifier(e){return 1===e.target.siblingsPosition?"group-identifier-"+e.source.stateIdentifier:null}enterSvgElements(e){if(this.textPosition=10,this.settings.showIcons){const e=Object.values(this.icons).filter(e=>""!==e.icon),t=this.iconsContainer.selectAll(".icon-def").data(e,e=>e.identifier);t.exit().remove(),t.enter().append("g").attr("class","icon-def").attr("id",e=>"icon-"+e.identifier).append(e=>{const t=new DOMParser,s='<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'+e.icon.replace("<svg","<g").replace("/svg>","/g>")+"</svg>";return t.parseFromString(s,"image/svg+xml").documentElement.firstChild})}const t=this.nodesUpdate(e);let s=t.append("g").attr("class","toggle").attr("visibility",this.getToggleVisibility).attr("transform","translate(-8, -8)").on("click",(e,t)=>this.chevronClick(t));if(s.append("path").style("opacity",0).attr("d","M 0 0 L 16 0 L 16 16 L 0 16 Z"),s.append("path").attr("class","chevron").attr("d","M 4 3 L 13 8 L 4 13 Z"),this.settings.showIcons){this.textPosition=30;const e=t.append("g").attr("class","node-icon-container").attr("title",this.getNodeTitle).attr("data-bs-toggle","tooltip").on("click",(e,t)=>{this.clickOnIcon(t)});e.append("use").attr("class","node-icon").attr("data-uid",this.getNodeIdentifier).attr("transform","translate(8, -8)"),e.append("use").attr("transform","translate(8, -3)").attr("class","node-icon-overlay"),e.append("use").attr("x",27).attr("y",-7).attr("class","node-icon-locked")}return d.initialize('[data-bs-toggle="tooltip"]',{delay:{show:50,hide:50},trigger:"hover",placement:"right"}),this.dispatch.call("updateSvg",this,t),this.appendTextElement(t),e.merge(t)}}}));