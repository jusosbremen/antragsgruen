define(["require","exports"],function(e,l){"use strict";Object.defineProperty(l,"__esModule",{value:!0});var t=function(){function e(e){this.$element=e,this.$holderElement=$(".well").first(),this.$element.click(this.toggleFullScreeen.bind(this))}return e.prototype.requestFullscreen=function(){var e=this.$holderElement[0];e.requestFullscreen?e.requestFullscreen():e.webkitRequestFullscreen?e.webkitRequestFullscreen():e.mozRequestFullScreen?e.mozRequestFullScreen():e.msRequestFullscreen&&e.msRequestFullscreen()},e.prototype.exitFullscreen=function(){var e=document;e.exitFullscreen?e.exitFullscreen():e.webkitExitFullscreen?e.webkitExitFullscreen():e.mozCancelFullScreen?e.mozCancelFullScreen():e.msExitFullscreen&&e.msExitFullscreen()},e.prototype.isFullscreen=function(){var e=document;return e.fullscreenElement||e.webkitFullscreenElement||e.mozFullScreenElement||e.msFullscreenElement},e.prototype.toggleFullScreeen=function(){this.isFullscreen()?this.exitFullscreen():this.requestFullscreen()},e}();l.FullscreenToggle=t});
//# sourceMappingURL=FullscreenToggle.js.map