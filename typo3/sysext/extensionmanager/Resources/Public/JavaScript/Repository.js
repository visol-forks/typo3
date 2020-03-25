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
define(["require","exports","jquery","nprogress","TYPO3/CMS/Backend/Modal","TYPO3/CMS/Backend/Notification","TYPO3/CMS/Backend/Severity","TYPO3/CMS/Core/Ajax/AjaxRequest","datatables","TYPO3/CMS/Backend/Input/Clearable"],(function(e,n,t,a,s,o,l,r){"use strict";return class{constructor(){this.downloadPath="",this.getDependencies=async e=>{const n=await e.resolve();a.done(),n.hasDependencies?s.confirm(n.title,t(n.message),l.info,[{text:TYPO3.lang["button.cancel"],active:!0,btnClass:"btn-default",trigger:()=>{s.dismiss()}},{text:TYPO3.lang["button.resolveDependencies"],btnClass:"btn-info",trigger:()=>{this.getResolveDependenciesAndInstallResult(n.url+"&tx_extensionmanager_tools_extensionmanagerextensionmanager[downloadPath]="+this.downloadPath),s.dismiss()}}]):n.hasErrors?o.error(n.title,n.message,15):this.getResolveDependenciesAndInstallResult(n.url+"&tx_extensionmanager_tools_extensionmanagerextensionmanager[downloadPath]="+this.downloadPath)}}initDom(){a.configure({parent:".module-loading-indicator",showSpinner:!1}),t("#terTable").DataTable({lengthChange:!1,pageLength:15,stateSave:!1,info:!1,paging:!1,searching:!1,ordering:!1,drawCallback:this.bindDownload}),t("#terVersionTable").DataTable({lengthChange:!1,pageLength:15,stateSave:!1,info:!1,paging:!1,searching:!1,drawCallback:this.bindDownload,order:[[2,"asc"]],columns:[{orderable:!1},{orderable:!1},{type:"version"},{orderable:!1},null,{orderable:!1}]}),t("#terSearchTable").DataTable({paging:!1,lengthChange:!1,stateSave:!1,info:!1,searching:!1,language:{search:"Filter results:"},ordering:!1,drawCallback:this.bindDownload}),this.bindDownload(),this.bindSearchFieldResetter()}bindDownload(){const e=t(".downloadFromTer form.download button[type=submit]");e.off("click"),e.on("click",e=>{e.preventDefault();const n=t(e.currentTarget).closest("form"),s=n.attr("data-href");this.downloadPath=n.find("input.downloadPath:checked").val(),a.start(),new r(s).get().then(this.getDependencies)})}getResolveDependenciesAndInstallResult(e){a.start(),new r(e).get().then(async e=>{const n=await e.raw().json();if(n.errorCount>0)s.confirm(n.errorTitle,t(n.errorMessage),l.error,[{text:TYPO3.lang["button.cancel"],active:!0,btnClass:"btn-default",trigger:()=>{s.dismiss()}},{text:TYPO3.lang["button.resolveDependenciesIgnore"],btnClass:"btn-danger disabled t3js-dependencies",trigger:e=>{t(e.currentTarget).hasClass("disabled")||(this.getResolveDependenciesAndInstallResult(n.skipDependencyUri),s.dismiss())}}]),s.currentModal.on("shown.bs.modal",()=>{const e=s.currentModal.find(".t3js-dependencies");t('input[name="unlockDependencyIgnoreButton"]',s.currentModal).on("change",n=>{e.toggleClass("disabled",!t(n.currentTarget).prop("checked"))})});else{let e=TYPO3.lang["extensionList.dependenciesResolveDownloadSuccess.message"+n.installationTypeLanguageKey].replace(/\{0\}/g,n.extension);e+="\n"+TYPO3.lang["extensionList.dependenciesResolveDownloadSuccess.header"]+": ",t.each(n.result,(n,a)=>{e+="\n\n"+TYPO3.lang["extensionList.dependenciesResolveDownloadSuccess.item"]+" "+n+": ",t.each(a,n=>{e+="\n* "+n})}),o.info(TYPO3.lang["extensionList.dependenciesResolveFlashMessage.title"+n.installationTypeLanguageKey].replace(/\{0\}/g,n.extension),e,15),top.TYPO3.ModuleMenu.App.refreshMenu()}}).finally(()=>{a.done()})}bindSearchFieldResetter(){let e;if(null!==(e=document.querySelector('.typo3-extensionmanager-searchTerForm input[type="text"]'))){const n=""!==e.value;e.clearable({onClear:e=>{n&&e.closest("form").submit()}})}}}}));