define(["require","exports","../shared/AntragsgruenEditor"],function(t,e,r){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var n=function(){function a(){}return a.removeEmptyParagraphs=function(){$(".paragraphHolder").each(function(t,e){0==e.childNodes.length&&$(e).remove()})},a.accept=function(t){var e=$(t);e.hasClass("ice-ins")&&a.insertAccept(t),e.hasClass("ice-del")&&a.deleteAccept(t)},a.reject=function(t){var e=$(t);e.hasClass("ice-ins")&&a.insertReject(e),e.hasClass("ice-del")&&a.deleteReject(e)},a.insertReject=function(t){var e,n=t[0].nodeName.toLowerCase();e="li"==n?t.parent():t,"ul"==n||"ol"==n||"li"==n||"blockquote"==n||"pre"==n||"p"==n?(e.css("overflow","hidden").height(e.height()),e.animate({height:"0"},250,function(){e.remove(),$(".collidingParagraph:empty").remove(),a.removeEmptyParagraphs()})):e.remove()},a.insertAccept=function(t){var e=$(t);e.removeClass("ice-cts ice-ins appendHint moved"),e.removeAttr("data-moving-partner data-moving-partner-id data-moving-partner-paragraph data-moving-msg"),"ul"!=t.nodeName.toLowerCase()&&"ol"!=t.nodeName.toLowerCase()||e.children().removeClass("ice-cts").removeClass("ice-ins").removeClass("appendHint"),"li"==t.nodeName.toLowerCase()&&e.parent().removeClass("ice-cts").removeClass("ice-ins").removeClass("appendHint"),"ins"==t.nodeName.toLowerCase()&&e.replaceWith(e.html())},a.deleteReject=function(t){t.removeClass("ice-cts ice-del appendHint"),t.removeAttr("data-moving-partner data-moving-partner-id data-moving-partner-paragraph data-moving-msg");var e=t[0].nodeName.toLowerCase();"ul"!=e&&"ol"!=e||t.children().removeClass("ice-cts").removeClass("ice-del").removeClass("appendHint"),"li"==e&&t.parent().removeClass("ice-cts").removeClass("ice-del").removeClass("appendHint"),"del"==e&&t.replaceWith(t.html())},a.deleteAccept=function(t){var e,n=t.nodeName.toLowerCase();e="li"==n?$(t).parent():$(t),"ul"==n||"ol"==n||"li"==n||"blockquote"==n||"pre"==n||"p"==n?(e.css("overflow","hidden").height(e.height()),e.animate({height:"0"},250,function(){e.remove(),$(".collidingParagraph:empty").remove(),a.removeEmptyParagraphs()})):e.remove()},a}();e.MotionMergeChangeActions=n;var a=function(){function t(i,r,o,t){this.$element=i,this.parent=t;var s=null,d=null;i.popover({container:"body",animation:!1,trigger:"manual",placement:function(t){var a=$(t);return window.setTimeout(function(){var t=a.width(),e=i.offset().top,n=i.height();null===s&&0<t&&(s=r-t/2,(d=o+10)<e+19&&(d=e+19),e+n<d&&(d=e+n)),a.css("left",s+"px"),a.css("top",d+"px")},1),"bottom"},html:!0,content:this.getContent.bind(this)}),i.popover("show"),i.find("> .popover").on("mousemove",function(t){t.stopPropagation()}),window.setTimeout(this.removePopupIfInactive.bind(this),1e3)}return t.prototype.getContent=function(){var t=this.$element,e=t.data("cid");null==e&&(e=t.parent().data("cid")),t.parents(".texteditor").first().find("[data-cid="+e+"]").addClass("hover");var n=$('<div><button type="button" class="accept btn btn-sm btn-default"></button><button type="button" class="reject btn btn-sm btn-default"></button><a href="#" class="btn btn-small btn-default opener" target="_blank"><span class="glyphicon glyphicon-new-window"></span></a><div class="initiator" style="font-size: 0.8em;"></div></div>');if(n.find(".opener").attr("href",t.data("link")).attr("title",__t("merge","title_open_in_blank")),n.find(".initiator").text(__t("merge","initiated_by")+": "+t.data("username")),t.hasClass("ice-ins"))n.find("button.accept").text(__t("merge","change_accept")).click(this.accept.bind(this)),n.find("button.reject").text(__t("merge","change_reject")).click(this.reject.bind(this));else if(t.hasClass("ice-del"))n.find("button.accept").text(__t("merge","change_accept")).click(this.accept.bind(this)),n.find("button.reject").text(__t("merge","change_reject")).click(this.reject.bind(this));else if("li"==t[0].nodeName.toLowerCase()){var a=t.parent();a.hasClass("ice-ins")?(n.find("button.accept").text(__t("merge","change_accept")).click(this.accept.bind(this)),n.find("button.reject").text(__t("merge","change_reject")).click(this.reject.bind(this))):a.hasClass("ice-del")?(n.find("button.accept").text(__t("merge","change_accept")).click(this.accept.bind(this)),n.find("button.reject").text(__t("merge","change_reject")).click(this.reject.bind(this))):console.log("unknown",a)}else console.log("unknown",t),alert("unknown");return n},t.prototype.removePopupIfInactive=function(){return this.$element.is(":hover")?window.setTimeout(this.removePopupIfInactive.bind(this),1e3):0<$("body").find(".popover:hover").length?window.setTimeout(this.removePopupIfInactive.bind(this),1e3):void this.destroy()},t.prototype.affectedChangesets=function(){var t=this.$element.data("cid");return null==t&&(t=this.$element.parent().data("cid")),this.$element.parents(".texteditor").find("[data-cid="+t+"]")},t.prototype.performActionWithUI=function(t){var e=window.scrollX,n=window.scrollY;this.parent.saveEditorSnapshot(),this.destroy(),t.call(this),$(".collidingParagraph:empty").remove(),this.parent.focusTextarea(),window.scrollTo(e,n)},t.prototype.accept=function(){var t=this;this.performActionWithUI(function(){t.affectedChangesets().each(function(t,e){n.accept(e)})})},t.prototype.reject=function(){var t=this;this.performActionWithUI(function(){t.affectedChangesets().each(function(t,e){n.reject(e)})})},t.prototype.destroy=function(){this.$element.popover("hide").popover("destroy");var t=this.$element.data("cid");null==t&&(t=this.$element.parent().data("cid")),this.$element.parents(".texteditor").first().find("[data-cid="+t+"]").removeClass("hover");try{var e=$(".popover");e.popover("hide").popover("destroy"),e.remove()}catch(t){}},t}(),i=function(){function t(t,e,n){this.$element=t,this.parent=n,t.popover({container:"body",animation:!1,trigger:"manual",placement:"bottom",html:!0,title:__t("merge","colliding_title"),content:this.getContent.bind(this)}),t.popover("show");var a=$("body > .popover"),i=a.width();a.css("left",Math.floor(t.offset().left+e-i/2+20)+"px"),a.on("mousemove",function(t){t.stopPropagation()}),window.setTimeout(this.removePopupIfInactive.bind(this),500)}return t.prototype.removePopupIfInactive=function(){return this.$element.is(":hover")?window.setTimeout(this.removePopupIfInactive.bind(this),1e3):0<$("body").find(".popover:hover").length?window.setTimeout(this.removePopupIfInactive.bind(this),1e3):void this.destroy()},t.prototype.performActionWithUI=function(t){this.parent.saveEditorSnapshot(),this.destroy(),t.call(this),$(".collidingParagraph:empty").remove(),this.parent.focusTextarea()},t.prototype.getContent=function(){var t=this,n=this.$element,e='<div style="white-space: nowrap;"><button type="button" class="btn btn-small btn-default delTitle"><span style="text-decoration: line-through">'+__t("merge","title")+"</span></button>";e+='<button type="button" class="reject btn btn-small btn-default"><span class="glyphicon glyphicon-trash"></span></button>',e+='<a href="#" class="btn btn-small btn-default opener" target="_blank"><span class="glyphicon glyphicon-new-window"></span></a>',e+='<div class="initiator" style="font-size: 0.8em;"></div>',e+="</div>";var a=$(e);return a.find(".delTitle").attr("title",__t("merge","title_del_title")),a.find(".reject").attr("title",__t("merge","title_del_colliding")),a.find("a.opener").attr("href",n.find("a").attr("href")).attr("title",__t("merge","title_open_in_blank")),a.find(".initiator").text(__t("merge","initiated_by")+": "+n.parents(".collidingParagraph").data("username")),a.find(".reject").click(function(){t.performActionWithUI.call(t,function(){var e=n.parents(".collidingParagraph");e.css({overflow:"hidden"}).height(e.height()),e.animate({height:"0"},250,function(){var t=e.parents(".paragraphHolder");e.remove(),0==t.find(".collidingParagraph").length&&t.removeClass("hasCollisions")})})}),a.find(".delTitle").click(function(){t.performActionWithUI.call(t,function(){var t=n.parents(".collidingParagraph");n.remove(),t.removeClass("collidingParagraph");var e=t.parents(".paragraphHolder");0==e.find(".collidingParagraph").length&&e.removeClass("hasCollisions")})}),a},t.prototype.destroy=function(){var t=this.$element.data("cid");null==t&&(t=this.$element.parent().data("cid")),this.$element.parents(".texteditor").first().find("[data-cid="+t+"]").removeClass("hover");try{var e=$(".popover");e.popover("hide").popover("destroy"),e.remove()}catch(t){}},t}(),o=function(){function t(t,e){var n=this;this.$holder=t,this.rootObject=e;var a=t.find(".texteditor"),i=new r.AntragsgruenEditor(a.attr("id"));this.texteditor=i.getEditor(),this.rootObject.addSubmitListener(function(){t.find("textarea.raw").val(n.texteditor.getData()),t.find("textarea.consolidated").val(n.texteditor.getData())}),this.setText(this.texteditor.getData()),this.$holder.find(".acceptAllChanges").click(this.acceptAll.bind(this)),this.$holder.find(".rejectAllChanges").click(this.rejectAll.bind(this))}return t.prototype.prepareText=function(t){var e=$("<div>"+t+"</div>");e.find("ul.appendHint, ol.appendHint").each(function(t,e){var n=$(e),a=n.data("append-hint");n.find("> li").addClass("appendHint").attr("data-append-hint",a).attr("data-link",n.data("link")).attr("data-username",n.data("username")),n.removeClass("appendHint").removeData("append-hint")}),e.find(".moved .moved").removeClass("moved"),e.find(".moved").each(this.markupMovedParagraph.bind(this)),e.find(".hasCollisions").attr("data-collision-start-msg",__t("merge","colliding_start")).attr("data-collision-end-msg",__t("merge","colliding_end"));var n=e.html();this.texteditor.setData(n)},t.prototype.markupMovedParagraph=function(t,e){var n,a=$(e),i=a.data("moving-partner-paragraph");n=(n=a.hasClass("inserted")?__t("std","moved_paragraph_from"):__t("std","moved_paragraph_to")).replace(/##PARA##/,i+1),"LI"===a[0].nodeName&&(a=a.parent()),a.attr("data-moving-msg",n)},t.prototype.initializeTooltips=function(){var e=this;this.$holder.on("mouseover",".collidingParagraphHead",function(t){$(t.target).parents(".collidingParagraph").addClass("hovered"),d.activePopup&&d.activePopup.destroy(),d.activePopup=new i($(t.currentTarget),d.currMouseX,e)}).on("mouseout",".collidingParagraphHead",function(t){$(t.target).parents(".collidingParagraph").removeClass("hovered")}),this.$holder.on("mouseover",".appendHint",function(t){d.activePopup&&d.activePopup.destroy(),d.activePopup=new a($(t.currentTarget),t.pageX,t.pageY,e)})},t.prototype.acceptAll=function(){this.texteditor.fire("saveSnapshot"),this.$holder.find(".collidingParagraph").each(function(t,e){var n=$(e);n.find(".collidingParagraphHead").remove(),n.replaceWith(n.children())}),this.$holder.find(".ice-ins").each(function(t,e){n.insertAccept(e)}),this.$holder.find(".ice-del").each(function(t,e){n.deleteAccept(e)})},t.prototype.rejectAll=function(){this.texteditor.fire("saveSnapshot"),this.$holder.find(".collidingParagraph").each(function(t,e){$(e).remove()}),this.$holder.find(".ice-ins").each(function(t,e){n.insertReject($(e))}),this.$holder.find(".ice-del").each(function(t,e){n.deleteReject($(e))})},t.prototype.saveEditorSnapshot=function(){this.texteditor.fire("saveSnapshot")},t.prototype.focusTextarea=function(){},t.prototype.getContent=function(){return this.texteditor.getData()},t.prototype.setText=function(t){this.prepareText(t),this.initializeTooltips()},t}(),s=function(){function t(t,e,n){this.$holder=t,this.textarea=e,this.amendmentStatuses=n,this.sectionId=parseInt(t.data("sectionId")),this.paragraphId=parseInt(t.data("paragraphId")),this.initButtons()}return t.prototype.hasChanged=function(){return!1},t.prototype.initButtons=function(){var n=this;this.$holder.find(".toggleAmendment").click(function(t){if(n.hasChanged())alert("TO DO");else{var e=$(t.currentTarget).find(".amendmentActive");1===parseInt(e.val())?(e.val("0"),e.parents(".btn-group").find(".btn").addClass("btn-default").removeClass("btn-success")):(e.val("1"),e.parents(".btn-group").find(".btn").removeClass("btn-default").addClass("btn-success")),n.reloadText()}}),this.$holder.find(".btn-group.amendmentStatus").on("show.bs.dropdown",function(t,e){console.log("onShow",t,e)})},t.prototype.reloadText=function(){var n=this,a=[];this.$holder.find(".amendmentActive[value='1']").each(function(t,e){a.push(parseInt($(e).data("amendment-id")))});var t=this.$holder.data("reload-url").replace("DUMMY",a.join(","));$.get(t,function(t){n.textarea.setText(t.text);var e="";t.collisions.forEach(function(t){e+=t}),n.$holder.find(".collisionsHolder").html(e),0<t.collisions.length?n.$holder.addClass("hasCollisions"):n.$holder.removeClass("hasCollisions")})},t}(),d=function(){function r(t){var i=this;this.$form=t,this.textareas={},this.amendmentStatuses=t.data("amendment-statuses"),console.log(this.amendmentStatuses),$(".paragraphWrapper").each(function(t,e){var n=$(e),a=n.find(".wysiwyg-textarea");i.textareas[a.attr("id")]=new o(a,i),a.on("mousemove",function(t){r.currMouseX=t.offsetX}),new s(n,i.textareas[a.attr("id")],i.amendmentStatuses)}),this.$form.on("submit",function(){$(window).off("beforeunload",r.onLeavePage)}),$(window).on("beforeunload",r.onLeavePage),this.initDraftSaving()}return r.onLeavePage=function(){return __t("std","leave_changed_page")},r.prototype.addSubmitListener=function(t){this.$form.submit(t)},r.prototype.setDraftDate=function(t){this.$draftSavingPanel.find(".lastSaved .none").hide();var e=$("html").attr("lang"),n=new Intl.DateTimeFormat(e,{year:"numeric",month:"numeric",day:"numeric",hour:"numeric",minute:"numeric",hour12:!1}).format(t);this.$draftSavingPanel.find(".lastSaved .value").text(n)},r.prototype.saveDraft=function(){for(var e=this,t={},n=0,a=Object.getOwnPropertyNames(this.textareas);n<a.length;n++){var i=a[n];t[i.replace("section_holder_","")]=this.textareas[i].getContent()}var r=this.$draftSavingPanel.find("input[name=public]").prop("checked");$.ajax({type:"POST",url:this.$form.data("draftSaving"),data:{public:r?1:0,sections:t,_csrf:this.$form.find("> input[name=_csrf]").val()},success:function(t){t.success?(e.$draftSavingPanel.find(".savingError").addClass("hidden"),e.setDraftDate(new Date(t.date)),r?e.$form.find(".publicLink").removeClass("hidden"):e.$form.find(".publicLink").addClass("hidden")):(e.$draftSavingPanel.find(".savingError").removeClass("hidden"),e.$draftSavingPanel.find(".savingError .errorNetwork").addClass("hidden"),e.$draftSavingPanel.find(".savingError .errorHolder").text(t.error).removeClass("hidden"))},error:function(){e.$draftSavingPanel.find(".savingError").removeClass("hidden"),e.$draftSavingPanel.find(".savingError .errorNetwork").removeClass("hidden"),e.$draftSavingPanel.find(".savingError .errorHolder").text("").addClass("hidden")}})},r.prototype.initAutosavingDraft=function(){var t=this,e=this.$draftSavingPanel.find("input[name=autosave]");if(window.setInterval(function(){e.prop("checked")&&t.saveDraft()},5e3),localStorage){var n=localStorage.getItem("merging-draft-auto-save");null!==n&&e.prop("checked","1"==n)}e.change(function(){var t=e.prop("checked");localStorage&&localStorage.setItem("merging-draft-auto-save",t?"1":"0")}).trigger("change")},r.prototype.initDraftSaving=function(){if(this.$draftSavingPanel=this.$form.find("#draftSavingPanel"),this.$draftSavingPanel.find(".saveDraft").on("click",this.saveDraft.bind(this)),this.$draftSavingPanel.find("input[name=public]").on("change",this.saveDraft.bind(this)),this.initAutosavingDraft(),this.$draftSavingPanel.data("resumed-date")){var t=new Date(this.$draftSavingPanel.data("resumed-date"));this.setDraftDate(t)}$("#yii-debug-toolbar").remove()},r.activePopup=null,r.currMouseX=null,r}();e.MotionMergeAmendments=d});
//# sourceMappingURL=MotionMergeAmendments.js.map
