define(["require","exports","../shared/AntragsgruenEditor"],function(e,t,i){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var n=function(){function e(){}return e.removeEmptyParagraphs=function(){$(".paragraphHolder").each(function(e,t){0==t.childNodes.length&&$(t).remove()})},e.accept=function(t){var i=$(t);i.hasClass("ice-ins")&&e.insertAccept(t),i.hasClass("ice-del")&&e.deleteAccept(t)},e.reject=function(t){var i=$(t);i.hasClass("ice-ins")&&e.insertReject(i),i.hasClass("ice-del")&&e.deleteReject(i)},e.insertReject=function(t){var i,n=t[0].nodeName.toLowerCase();i="li"==n?t.parent():t,"ul"==n||"ol"==n||"li"==n||"blockquote"==n||"pre"==n||"p"==n?(i.css("overflow","hidden").height(i.height()),i.animate({height:"0"},250,function(){i.remove(),$(".collidingParagraph:empty").remove(),e.removeEmptyParagraphs()})):i.remove()},e.insertAccept=function(e){var t=$(e);t.removeClass("ice-cts ice-ins appendHint moved"),t.removeAttr("data-moving-partner data-moving-partner-id data-moving-partner-paragraph data-moving-msg"),"ul"!=e.nodeName.toLowerCase()&&"ol"!=e.nodeName.toLowerCase()||t.children().removeClass("ice-cts").removeClass("ice-ins").removeClass("appendHint"),"li"==e.nodeName.toLowerCase()&&t.parent().removeClass("ice-cts").removeClass("ice-ins").removeClass("appendHint"),"ins"==e.nodeName.toLowerCase()&&t.replaceWith(t.html())},e.deleteReject=function(e){e.removeClass("ice-cts ice-del appendHint"),e.removeAttr("data-moving-partner data-moving-partner-id data-moving-partner-paragraph data-moving-msg");var t=e[0].nodeName.toLowerCase();"ul"!=t&&"ol"!=t||e.children().removeClass("ice-cts").removeClass("ice-del").removeClass("appendHint"),"li"==t&&e.parent().removeClass("ice-cts").removeClass("ice-del").removeClass("appendHint"),"del"==t&&e.replaceWith(e.html())},e.deleteAccept=function(t){var i,n=t.nodeName.toLowerCase();i="li"==n?$(t).parent():$(t),"ul"==n||"ol"==n||"li"==n||"blockquote"==n||"pre"==n||"p"==n?(i.css("overflow","hidden").height(i.height()),i.animate({height:"0"},250,function(){i.remove(),$(".collidingParagraph:empty").remove(),e.removeEmptyParagraphs()})):i.remove()},e}();t.MotionMergeChangeActions=n;var a=function(){function e(e,t,i,n){this.$element=e,this.parent=n;var a=null,r=null;e.popover({container:"body",animation:!1,trigger:"manual",placement:function(n){var o=$(n);return window.setTimeout(function(){var n=o.width(),s=e.offset().top,d=e.height();null===a&&n>0&&(a=t-n/2,(r=i+10)<s+19&&(r=s+19),r>s+d&&(r=s+d)),o.css("left",a+"px"),o.css("top",r+"px")},1),"bottom"},html:!0,content:this.getContent.bind(this)}),e.popover("show"),e.find("> .popover").on("mousemove",function(e){e.stopPropagation()}),window.setTimeout(this.removePopupIfInactive.bind(this),1e3)}return e.prototype.getContent=function(){var e,t=this.$element,i=t.data("cid");void 0==i&&(i=t.parent().data("cid")),t.parents(".texteditor").first().find("[data-cid="+i+"]").addClass("hover"),e="<div>",e+='<button type="button" class="accept btn btn-sm btn-default"></button>',e+='<button type="button" class="reject btn btn-sm btn-default"></button>',e+='<a href="#" class="btn btn-small btn-default opener" target="_blank"><span class="glyphicon glyphicon-new-window"></span></a>',e+='<div class="initiator" style="font-size: 0.8em;"></div>',e+="</div>";var n=$(e);if(n.find(".opener").attr("href",t.data("link")).attr("title",__t("merge","title_open_in_blank")),n.find(".initiator").text(__t("merge","initiated_by")+": "+t.data("username")),t.hasClass("ice-ins"))n.find("button.accept").text(__t("merge","change_accept")).click(this.accept.bind(this)),n.find("button.reject").text(__t("merge","change_reject")).click(this.reject.bind(this));else if(t.hasClass("ice-del"))n.find("button.accept").text(__t("merge","change_accept")).click(this.accept.bind(this)),n.find("button.reject").text(__t("merge","change_reject")).click(this.reject.bind(this));else if("li"==t[0].nodeName.toLowerCase()){var a=t.parent();a.hasClass("ice-ins")?(n.find("button.accept").text(__t("merge","change_accept")).click(this.accept.bind(this)),n.find("button.reject").text(__t("merge","change_reject")).click(this.reject.bind(this))):a.hasClass("ice-del")?(n.find("button.accept").text(__t("merge","change_accept")).click(this.accept.bind(this)),n.find("button.reject").text(__t("merge","change_reject")).click(this.reject.bind(this))):console.log("unknown",a)}else console.log("unknown",t),alert("unknown");return n},e.prototype.removePopupIfInactive=function(){return this.$element.is(":hover")?window.setTimeout(this.removePopupIfInactive.bind(this),1e3):$("body").find(".popover:hover").length>0?window.setTimeout(this.removePopupIfInactive.bind(this),1e3):void this.destroy()},e.prototype.affectedChangesets=function(){var e=this.$element.data("cid");return void 0==e&&(e=this.$element.parent().data("cid")),this.$element.parents(".texteditor").find("[data-cid="+e+"]")},e.prototype.performActionWithUI=function(e){var t=window.scrollX,i=window.scrollY;this.parent.saveEditorSnapshot(),this.destroy(),e.call(this),$(".collidingParagraph:empty").remove(),this.parent.focusTextarea(),window.scrollTo(t,i)},e.prototype.accept=function(){var e=this;this.performActionWithUI(function(){e.affectedChangesets().each(function(e,t){n.accept(t)})})},e.prototype.reject=function(){var e=this;this.performActionWithUI(function(){e.affectedChangesets().each(function(e,t){n.reject(t)})})},e.prototype.destroy=function(){this.$element.popover("hide").popover("destroy");var e=this.$element.data("cid");void 0==e&&(e=this.$element.parent().data("cid")),this.$element.parents(".texteditor").first().find("[data-cid="+e+"]").removeClass("hover")},e}(),r=function(){function e(e,t,i){this.$element=e,this.parent=i,e.popover({container:"body",animation:!1,trigger:"manual",placement:"bottom",html:!0,title:__t("merge","colliding_title"),content:this.getContent.bind(this)}),e.popover("show");var n=$("body > .popover"),a=n.width();n.css("left",Math.floor(e.offset().left+t-a/2+20)+"px"),n.on("mousemove",function(e){e.stopPropagation()}),window.setTimeout(this.removePopupIfInactive.bind(this),500)}return e.prototype.removePopupIfInactive=function(){return this.$element.is(":hover")?window.setTimeout(this.removePopupIfInactive.bind(this),1e3):$("body").find(".popover:hover").length>0?window.setTimeout(this.removePopupIfInactive.bind(this),1e3):void this.destroy()},e.prototype.performActionWithUI=function(e){this.parent.saveEditorSnapshot(),this.destroy(),e.call(this),$(".collidingParagraph:empty").remove(),this.parent.focusTextarea()},e.prototype.getContent=function(){var e=this,t=this.$element,i='<div style="white-space: nowrap;"><button type="button" class="btn btn-small btn-default delTitle"><span style="text-decoration: line-through">'+__t("merge","title")+"</span></button>";i+='<button type="button" class="reject btn btn-small btn-default"><span class="glyphicon glyphicon-trash"></span></button>',i+='<a href="#" class="btn btn-small btn-default opener" target="_blank"><span class="glyphicon glyphicon-new-window"></span></a>',i+='<div class="initiator" style="font-size: 0.8em;"></div>',i+="</div>";var n=$(i);return n.find(".delTitle").attr("title",__t("merge","title_del_title")),n.find(".reject").attr("title",__t("merge","title_del_colliding")),n.find("a.opener").attr("href",t.find("a").attr("href")).attr("title",__t("merge","title_open_in_blank")),n.find(".initiator").text(__t("merge","initiated_by")+": "+t.parents(".collidingParagraph").data("username")),n.find(".reject").click(function(){e.performActionWithUI.call(e,function(){var e=t.parents(".collidingParagraph");e.css({overflow:"hidden"}).height(e.height()),e.animate({height:"0"},250,function(){var t=e.parents(".paragraphHolder");e.remove(),0==t.find(".collidingParagraph").length&&t.removeClass("hasCollissions")})})}),n.find(".delTitle").click(function(){e.performActionWithUI.call(e,function(){var e=t.parents(".collidingParagraph");t.remove(),e.removeClass("collidingParagraph");var i=e.parents(".paragraphHolder");0==i.find(".collidingParagraph").length&&i.removeClass("hasCollissions")})}),n},e.prototype.destroy=function(){var e=this.$element.data("cid");void 0==e&&(e=this.$element.parent().data("cid")),this.$element.parents(".texteditor").first().find("[data-cid="+e+"]").removeClass("hover"),this.$element.popover("hide").popover("destroy")},e}(),o=function(){function e(e,t){var n=this;this.$holder=e,this.rootObject=t;var a=e.find(".texteditor"),r=new i.AntragsgruenEditor(a.attr("id"));this.texteditor=r.getEditor(),this.rootObject.addSubmitListener(function(){e.find("textarea.raw").val(n.texteditor.getData()),e.find("textarea.consolidated").val(n.texteditor.getData())}),this.prepareText(),this.initializeTooltips(),this.$holder.find(".acceptAllChanges").click(this.acceptAll.bind(this)),this.$holder.find(".rejectAllChanges").click(this.rejectAll.bind(this))}return e.prototype.prepareText=function(){var e=$("<div>"+this.texteditor.getData()+"</div>");e.find("ul.appendHint, ol.appendHint").each(function(e,t){var i=$(t),n=i.data("append-hint");i.find("> li").addClass("appendHint").attr("data-append-hint",n).attr("data-link",i.data("link")).attr("data-username",i.data("username")),i.removeClass("appendHint").removeData("append-hint")}),e.find(".moved .moved").removeClass("moved"),e.find(".moved").each(this.markupMovedParagraph.bind(this)),e.find(".hasCollissions").attr("data-collission-start-msg",__t("merge","colliding_start")).attr("data-collission-end-msg",__t("merge","colliding_end"));var t=e.html();this.texteditor.setData(t)},e.prototype.markupMovedParagraph=function(e,t){var i,n=$(t),a=n.data("moving-partner-paragraph");i=(i=n.hasClass("inserted")?__t("std","moved_paragraph_from"):__t("std","moved_paragraph_to")).replace(/##PARA##/,a+1),"LI"===n[0].nodeName&&(n=n.parent()),n.attr("data-moving-msg",i)},e.prototype.initializeTooltips=function(){var e=this;this.$holder.on("mouseover",".collidingParagraphHead",function(t){$(t.target).parents(".collidingParagraph").addClass("hovered"),s.activePopup&&s.activePopup.destroy(),s.activePopup=new r($(t.currentTarget),s.currMouseX,e)}).on("mouseout",".collidingParagraphHead",function(e){$(e.target).parents(".collidingParagraph").removeClass("hovered")}),this.$holder.on("mouseover",".appendHint",function(t){s.activePopup&&s.activePopup.destroy(),s.activePopup=new a($(t.currentTarget),t.pageX,t.pageY,e)})},e.prototype.acceptAll=function(){this.texteditor.fire("saveSnapshot"),this.$holder.find(".collidingParagraph").each(function(e,t){var i=$(t);i.find(".collidingParagraphHead").remove(),i.replaceWith(i.children())}),this.$holder.find(".ice-ins").each(function(e,t){n.insertAccept(t)}),this.$holder.find(".ice-del").each(function(e,t){n.deleteAccept(t)})},e.prototype.rejectAll=function(){this.texteditor.fire("saveSnapshot"),this.$holder.find(".collidingParagraph").each(function(e,t){$(t).remove()}),this.$holder.find(".ice-ins").each(function(e,t){n.insertReject($(t))}),this.$holder.find(".ice-del").each(function(e,t){n.deleteReject($(t))})},e.prototype.saveEditorSnapshot=function(){this.texteditor.fire("saveSnapshot")},e.prototype.focusTextarea=function(){},e.prototype.getContent=function(){return this.texteditor.getData()},e}(),s=function(){function e(t){var i=this;this.$form=t,this.textareas={},$(".wysiwyg-textarea").each(function(t,n){var a=$(n);i.textareas[a.attr("id")]=new o(a,i),a.on("mousemove",function(t){e.currMouseX=t.offsetX})}),this.$form.on("submit",function(){$(window).off("beforeunload",e.onLeavePage)}),$(window).on("beforeunload",e.onLeavePage),this.initDraftSaving()}return e.onLeavePage=function(){return __t("std","leave_changed_page")},e.prototype.addSubmitListener=function(e){this.$form.submit(e)},e.prototype.setDraftDate=function(e){this.$draftSavingPanel.find(".lastSaved .none").hide();var t={year:"numeric",month:"numeric",day:"numeric",hour:"numeric",minute:"numeric",hour12:!1},i=$("html").attr("lang"),n=new Intl.DateTimeFormat(i,t).format(e);this.$draftSavingPanel.find(".lastSaved .value").text(n)},e.prototype.saveDraft=function(){for(var e=this,t={},i=0,n=Object.getOwnPropertyNames(this.textareas);i<n.length;i++){var a=n[i];t[a.replace("section_holder_","")]=this.textareas[a].getContent()}var r=this.$draftSavingPanel.find("input[name=public]").prop("checked");$.ajax({type:"POST",url:this.$form.data("draftSaving"),data:{public:r?1:0,sections:t,_csrf:this.$form.find("> input[name=_csrf]").val()},success:function(t){t.success?(e.$draftSavingPanel.find(".savingError").addClass("hidden"),e.setDraftDate(new Date(t.date)),r?e.$form.find(".publicLink").removeClass("hidden"):e.$form.find(".publicLink").addClass("hidden")):(e.$draftSavingPanel.find(".savingError").removeClass("hidden"),e.$draftSavingPanel.find(".savingError .errorNetwork").addClass("hidden"),e.$draftSavingPanel.find(".savingError .errorHolder").text(t.error).removeClass("hidden"))},error:function(){e.$draftSavingPanel.find(".savingError").removeClass("hidden"),e.$draftSavingPanel.find(".savingError .errorNetwork").removeClass("hidden"),e.$draftSavingPanel.find(".savingError .errorHolder").text("").addClass("hidden")}})},e.prototype.initAutosavingDraft=function(){var e=this,t=this.$draftSavingPanel.find("input[name=autosave]");if(window.setInterval(function(){t.prop("checked")&&e.saveDraft()},5e3),localStorage){var i=localStorage.getItem("merging-draft-auto-save");null!==i&&t.prop("checked","1"==i)}t.change(function(){var e=t.prop("checked");localStorage&&localStorage.setItem("merging-draft-auto-save",e?"1":"0")}).trigger("change")},e.prototype.initDraftSaving=function(){if(this.$draftSavingPanel=this.$form.find("#draftSavingPanel"),this.$draftSavingPanel.find(".saveDraft").on("click",this.saveDraft.bind(this)),this.$draftSavingPanel.find("input[name=public]").on("change",this.saveDraft.bind(this)),this.initAutosavingDraft(),this.$draftSavingPanel.data("resumed-date")){var e=new Date(this.$draftSavingPanel.data("resumed-date"));this.setDraftDate(e)}$("#yii-debug-toolbar").remove()},e}();s.activePopup=null,s.currMouseX=null,t.MotionMergeAmendments=s});
//# sourceMappingURL=MotionMergeAmendments.js.map
