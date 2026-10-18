(function () {
	"use strict";

	function normalizeHex(value) {
		value = String(value || "").trim();
		if (value.charAt(0) !== "#") value = "#" + value;
		if (/^#[0-9a-f]{3}$/i.test(value)) value = "#" + value.slice(1).split("").map(function (character) { return character + character; }).join("");
		return /^#[0-9a-f]{6}$/i.test(value) ? value.toLowerCase() : "";
	}

	document.addEventListener("DOMContentLoaded", function () {
		var guideButton = document.getElementById("yoohw-shortcode-guide-open");
		if (guideButton && typeof window.tb_show === "function") {
			guideButton.addEventListener("click", function () {
				window.tb_show(guideButton.dataset.dialogTitle, "#TB_inline?inlineId=yoohw-shortcode-guide&width=700&height=560");
				var dialog = document.getElementById("TB_window");
				if (!dialog) return;
				dialog.classList.add("yoohw-shortcode-modal");
				dialog.setAttribute("role", "dialog");
				dialog.setAttribute("aria-modal", "true");
				dialog.setAttribute("aria-labelledby", "TB_ajaxWindowTitle");
				document.getElementById("TB_ajaxContent").tabIndex = 0;
				// Keep keyboard navigation inside the dialog, including its scrollable guide.
				dialog.addEventListener("keydown", function (event) {
					if (event.key === "Tab") {
						event.preventDefault();
						var close = document.getElementById("TB_closeWindowButton");
						var guide = document.getElementById("TB_ajaxContent");
						(document.activeElement === close ? guide : close).focus();
					}
				});
				window.jQuery(dialog).one("tb_unload", function () { guideButton.focus(); });
			});
		}

		document.querySelectorAll("[data-yoohw-color-field]").forEach(function (field) {
			var picker = field.querySelector("[data-yoohw-color-picker]");
			var hex = field.querySelector("[data-yoohw-color-hex]");
			if (!picker || !hex) return;
			picker.addEventListener("input", function () { hex.value = picker.value; });
			hex.addEventListener("input", function () { var value = normalizeHex(hex.value); if (value) picker.value = value; });
			hex.addEventListener("blur", function () { hex.value = normalizeHex(hex.value) || picker.value; });
		});

		if (!window.YoOhwSupportAdmin) return;
		var config = window.YoOhwSupportAdmin;
		document.querySelectorAll('#post_status, .inline-edit-row select[name="_status"], #bulk-edit select[name="_status"]').forEach(function (select) {
			if (!select.querySelector('option[value="' + config.status + '"]')) {
				var option = document.createElement("option");
				option.value = config.status;
				option.textContent = config.label;
				select.appendChild(option);
			}
		});
		if (config.currentStatus === config.status) {
			var statusSelect = document.getElementById("post_status");
			var display = document.getElementById("post-status-display");
			if (statusSelect) statusSelect.value = config.status;
			if (display) display.textContent = config.label;
		}
	});
})();
