class IMotionShow{initContactShow(){$(".motionData .contactShow").on("click",(function(e){e.preventDefault(),$(this).addClass("hidden"),$(".motionData .contactDetails").removeClass("hidden")}))}initAmendmentTextMode(){$(".amendmentTextModeSelector a.showOnlyChanges").on("click",(e=>{const t=$(e.target).parents(".motionTextHolder");t.find(".amendmentTextModeSelector .showOnlyChanges").parent().addClass("selected"),t.find(".amendmentTextModeSelector .showFullText").parent().removeClass("selected"),t.find(".fullMotionText").addClass("hidden"),t.find(".onlyChangedText").removeClass("hidden"),e.preventDefault()})),$(".amendmentTextModeSelector a.showFullText").on("click",(e=>{const t=$(e.target).parents(".motionTextHolder");t.find(".amendmentTextModeSelector .showOnlyChanges").parent().removeClass("selected"),t.find(".amendmentTextModeSelector .showFullText").parent().addClass("selected"),t.find(".fullMotionText").removeClass("hidden"),t.find(".onlyChangedText").addClass("hidden"),e.preventDefault()}))}initDelSubmit(){$("form.delLink").on("submit",(e=>{e.preventDefault();let t=e.target;bootbox.confirm(__t("std","del_confirm"),(function(e){e&&t.submit()}))}))}initCmdEnterSubmit(){$(document).on("keypress","form textarea",(e=>{if(console.log(e.originalEvent),e.originalEvent.metaKey&&13===e.originalEvent.keyCode){$(e.currentTarget).parents("form").first().find("button[type=submit]").trigger("click")}}))}}
//# sourceMappingURL=IMotionShow.js.map
