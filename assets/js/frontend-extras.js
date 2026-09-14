(function () {
	"use strict";

	function enhanceCodeBlocks(root) {
		if (!root) return;

		root.querySelectorAll("pre").forEach(function (pre) {
			if (pre.closest(".yoohw-codeblock")) return;

			var wrapper = document.createElement("div");
			var toolbar = document.createElement("div");
			var label = document.createElement("span");
			var wrapButton = document.createElement("button");
			var copyButton = document.createElement("button");
			var code = pre.querySelector("code") || pre;

			wrapper.className = "yoohw-codeblock";
			toolbar.className = "yoohw-codeblock-toolbar";
			label.className = "yoohw-codeblock-lang";
			label.textContent = "code";
			wrapButton.type = "button";
			wrapButton.className = "yoohw-codeblock-btn";
			wrapButton.textContent = "Wrap";
			wrapButton.setAttribute("aria-pressed", "false");
			copyButton.type = "button";
			copyButton.className = "yoohw-codeblock-btn";
			copyButton.textContent = "Copy";

			pre.parentNode.insertBefore(wrapper, pre);
			wrapper.appendChild(pre);
			toolbar.appendChild(label);
			toolbar.appendChild(wrapButton);
			toolbar.appendChild(copyButton);
			wrapper.insertBefore(toolbar, pre);

			wrapButton.addEventListener("click", function () {
				var enabled = wrapper.classList.toggle("is-wrapped");
				wrapButton.setAttribute("aria-pressed", enabled ? "true" : "false");
			});

			copyButton.addEventListener("click", function () {
				var text = code.innerText || code.textContent || "";
				if (!navigator.clipboard || !navigator.clipboard.writeText) return;
				navigator.clipboard.writeText(text).then(function () {
					copyButton.textContent = "Copied!";
					window.setTimeout(function () { copyButton.textContent = "Copy"; }, 1200);
				});
			});
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		var form = document.querySelector("form#commentform, form.comment-form");
		var bubble = document.querySelector(".yoohw-latest-comment-bubble");
		var passwordToggle = document.getElementById("toggle-password");

		if (form) {
			form.noValidate = true;
			form.setAttribute("enctype", "multipart/form-data");
			form.addEventListener("submit", function (event) {
				if (form.dataset.yoohwSubmitting === "1") {
					event.preventDefault();
					return;
				}
				form.dataset.yoohwSubmitting = "1";
				if (window.tinymce) window.tinymce.triggerSave();
			});
		}

		if (bubble) {
			bubble.addEventListener("click", function (event) {
				var target = document.querySelector(bubble.getAttribute("href"));
				if (!target) return;
				event.preventDefault();
				target.scrollIntoView({ behavior: "smooth", block: "start" });
			});
		}

		if (passwordToggle) {
			passwordToggle.addEventListener("click", function () {
				var field = document.getElementById("user_pass");
				if (field) field.type = field.type === "password" ? "text" : "password";
			});
		}

		document.querySelectorAll(".wp-block-comment-content,.comment-content,.entry-content,.post-content,.yoohw-post-content,.wp-block-post-content").forEach(enhanceCodeBlocks);
	});
})();
