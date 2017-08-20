define(["require","exports","../shared/AntragsgruenEditor","../frontend/MotionMergeAmendments"],function(t,e,n,i){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var o=function(){function t(e){this.$form=e,this.hasChanged=!1,this.textEditCalled(),this.initCollissionDetection(),e.on("submit",function(){$(window).off("beforeunload",t.onLeavePage)})}return t.prototype.textEditCalled=function(){var t=this;$(".wysiwyg-textarea:not(#sectionHolderEditorial)").each(function(e,i){var o=$(i).find(".texteditor"),a=new n.AntragsgruenEditor(o.attr("id")).getEditor();o.parents("form").submit(function(){o.parent().find("textarea.raw").val(a.getData()),void 0!==a.plugins.lite&&(a.plugins.lite.findPlugin(a).acceptAll(),o.parent().find("textarea.consolidated").val(a.getData()))}),$("#"+o.attr("id")).on("keypress",t.onContentChanged.bind(t))}),this.$form.find(".resetText").click(function(t){var e=$(t.currentTarget).parents(".wysiwyg-textarea").find(".texteditor");window.CKEDITOR.instances[e.attr("id")].setData(e.data("original-html")),$(t.currentTarget).parents(".modifiedActions").addClass("hidden")})},t.prototype.initCollissionDetection=function(){var t=this;this.$collissionIndicator=this.$form.find("#collissionIndicator"),window.setInterval(function(){var e=t.getTextConsolidatedSections(),n=t.$form.data("collission-check-url");$.post(n,{_csrf:t.$form.find("> input[name=_csrf]").val(),sections:e},function(e){if(0==e.collissions.length)t.$collissionIndicator.addClass("hidden");else{t.$collissionIndicator.removeClass("hidden");var n="";e.collissions.forEach(function(t){n+=t.html}),t.$collissionIndicator.find(".collissionList").html(n)}})},5e3)},t.prototype.getTextConsolidatedSections=function(){var t={};return $(".proposedVersion .wysiwyg-textarea:not(#sectionHolderEditorial)").each(function(e,n){var o=$(n),a=o.find(".texteditor"),s=o.parents(".proposedVersion").data("section-id"),r=a.clone(!1);r.find(".ice-ins").each(function(t,e){i.MotionMergeChangeActions.insertAccept(e)}),r.find(".ice-del").each(function(t,e){i.MotionMergeChangeActions.deleteAccept(e)}),t[s]=r.html()}),t},t.onLeavePage=function(){return __t("std","leave_changed_page")},t.prototype.onContentChanged=function(){this.hasChanged||(this.hasChanged=!0,$("body").hasClass("testing")||$(window).on("beforeunload",t.onLeavePage))},t}();e.AmendmentEditProposedChange=o});
//# sourceMappingURL=AmendmentEditProposedChange.js.map
