/**
 * center.js
 * 个人中心
 * IBOS
 * @author		inaki
 * @version		$Id$
 */

/**
 * 动态进度条，使进度条有从0开始读取的效果
 * @param {Jquery} $elem   容器节点
 * @param {Number} value   初始值
 * @param {Object} options 配置项
 */
var Progress = function($elem, value, options) {
	this.$elem = $elem;
	this.value = this._reviseValue(value);
	this.options = $.extend({}, Progress.defaults, options);
	this.style = "";
	this._init();
}
Progress.defaults = {
	roll: true,
	speed: 20,
	active: false
};
Progress.prototype = {
	constractor: Progress,
	_init: function() {
		this.$elem.addClass("progress");
		this.$progress = this.$elem.find(".progress-bar");
		if (this.$progress.length === 0) {
			this.$progress = $("<div class='progress-bar'></div>").appendTo(this.$elem);
		}
		this.setStyle(this.options.style);
		this.setActive(this.options.active);
		if (!isNaN(this.value)) {
			this._setValue();
		}
	},
	/**
	 * 修正值的大小，值必须在0到100之间
	 * @param  {[type]} value [description]
	 * @return {[type]}       [description]
	 */
	_reviseValue: function(value) {
		value = parseInt(value, 10);
		// NaN
		value = value < 0 ? 0 : value > 100 ? 100 : value;
		return value;
	},
	setStyle: function(style) {
		var styles = ["danger", "info", "warning", "success"],
				styleStr = "",
				pre = "progress-bar-";

		if (this.style !== style) {
			this.style = style;
			for (var i = styles.length; i--; ) {
				styleStr += pre + styles[i] + " ";
			}
			this.$progress.removeClass(styleStr);

			if ($.inArray(style, styles) !== -1) {
				this.$progress.addClass(pre + style);
			}
		}
	},
	setActive: function(toStriped) {
		this.$elem.toggleClass("progress-striped", toStriped);
		this.$elem.toggleClass("active", toStriped);
	},
	_setValue: function() {
		if (!isNaN(this.value)) {
			// 动态进度条
			if (this.options.roll) {
				var that = this,
						interval = this.options.speed,
						current = 0,
						transTemp,
						timer;
				// 由于css3的transition会与setInterval计算冲突，transitionEnd回调不兼容，所以先去掉该属性
				transTemp = this.$progress.css("transition");
				this.$progress.css("transition", "none");

				that.$elem.trigger("rollstart");

				timer = setInterval(function() {
					that.$progress.css("width", current + "%");
					that.$elem.trigger("rolling", {
						value: current
					});
					if (current >= that.value) {
						clearInterval(timer);
						that.$elem.trigger("rollend");
						that.$progress.css("transition", transTemp);
					}
					current++;
				}, interval);
			} else {
				this.$progress.css("width", this.value + "%");
			}
		}

	},
	setValue: function(value) {
		this.value = this._reviseValue(value);
		this._setValue();
	}
};

var userCenter = {
	op: {
		// 绑定酷办公
		"bindIbosco": function(param){
			var url = Ibos.app.url("user/home/bindco");
			return $.post(url, param, $.noop, "json");
		},
		// 解绑酷办公 
		"relieveIbosco": function(param){
			var url = Ibos.app.url("user/home/unbindco");
			return $.post(url, param, $.noop, "json");
		}
	}
}

$(function() {
	Ibos.evt.add({
		//  绑定手机、邮箱
		"bind": function(param, elem) {
			var dialog = Ui.dialog({
				id: 'bind_box',
				title: Ibos.l("USER.BIND_OPERATION"),
				width: '500px',
				cancel: true,
				ok: function() {
					var $verify = $('#inputVerify'),
						verify = $verify.val();
					if ($.trim(verify) === '') {
						$verify.blink().focus();
						return false;
					}
					$.get(Ibos.app.url("user/home/checkVerify", {
						uid: Ibos.app.g("currentUid")
					}), {
						data: encodeURI(verify),
						op: param.type
					}, function(res) {
						if (res.isSuccess) {
							Ui.tip('@OPERATION_SUCCESS');
							_dialog.close();
							window.location.reload();
						} else {
							Ui.tip('@OPERATION_FAILED', 'danger');
							return false;
						}
					}, 'json');

					return false;
				}
			});
			// 加载对话框内容
			$.ajax({
				url: Ibos.app.url("user/home/bind", {
					uid: Ibos.app.g("currentUid")
				}),
				data: {
					op: param.type
				},
				success: function(res) {
					dialog.content(res);
				},
				cache: false
			});
		},
		// 绑定酷办公账号
		"bindIbosco": function(param, elem) {
			var url = Ibos.app.url('user/home/show', {uid: Ibos.app.g("currentUid")}),
			dialog = Ui.ajaxDialog(url, {
				title: Ibos.l("USER.BIND_OPERATION"),
				id: "ibosco_bind",
				width: '500px',
				cancel: true,
				ok: function() {
					var _dialog = this,
						$account = $("#account"),
						$password = $("#password"),
						account = $account.val(),
						password = $password.val();
					// 验证账号不为空
					if($.trim(account) === ""){
						$account.blink().focus();
						return false;
					}
					// 验证密码不为空
					if($.trim(password) === ""){
						$password.blink().focus();
						return false;
					}

					var param = {account: account, password: password};
					userCenter.op.bindIbosco(param).done(function(res){
						if (res.isSuccess) {
							Ui.tip(res.msg);
							_dialog.close();
							window.location.reload();
						} else {
							Ui.tip(res.msg, "danger");
						}
					});
					return false;
				}
			});
		},
		// 解绑酷办公账号
		"relieveIbosco": function(param, elem) {
			var confirm = Ui.confirm(Ibos.l("USER.SUER_UNBIND_IBOSCO"), function() {
				var uid = Ibos.app.g("currentUid"),
					param = {uid: uid};
				userCenter.op.relieveIbosco(param).done(function(res){
					if (res.isSuccess) {
						Ui.tip(res.msg);
						window.location.reload();
					} else {
						Ui.tip(res.msg, "danger");
						return false;
					}
				});
			});
		}
	});
});