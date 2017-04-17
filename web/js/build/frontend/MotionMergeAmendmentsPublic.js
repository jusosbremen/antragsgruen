define(["require","exports"],function(t,e){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var i=function(){function t(t){this.$widget=t,this.interval=null,this.initUpdateWidget()}return t.prototype.showUpdated=function(){var t=this.$updateWidget.find(".updated");t.addClass("active"),window.setTimeout(function(){t.removeClass("active")},2e3)},t.prototype.reload=function(t){var e=this;$.get(this.updateUrl,function(i){if(!i.success)return void alert(i.error);e.$draftContent.html(i.html),e.$dateField.text(i.date),t&&e.showUpdated()})},t.prototype.startInterval=function(){null===this.interval&&(this.interval=window.setInterval(this.reload.bind(this,!1),5e3))},t.prototype.stopInterval=function(){null!==this.interval&&(window.clearInterval(this.interval),this.interval=null)},t.prototype.initUpdateWidget=function(){var t=this;this.$updateWidget=this.$widget.find(".motionUpdateWidget"),this.$draftContent=this.$widget.find(".draftContent"),this.$dateField=this.$widget.find(".mergeDraftDate"),this.updateUrl=this.$widget.data("reload-url");var e=this.$updateWidget.find("#autoUpdateToggle");if(localStorage){var i=localStorage.getItem("merging-draft-auto-update");null!==i&&e.prop("checked","1"==i)}e.change(function(){var i=e.prop("checked");localStorage&&localStorage.setItem("merging-draft-auto-update",i?"1":"0"),i?t.startInterval():t.stopInterval()}).trigger("change"),this.$updateWidget.find("#updateBtn").click(this.reload.bind(this,!0))},t}();e.MotionMergeAmendmentsPublic=i});
//# sourceMappingURL=MotionMergeAmendmentsPublic.js.map
