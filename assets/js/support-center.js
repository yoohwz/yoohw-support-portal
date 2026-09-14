(function () {
  function config() {
    return window.YoOhwSupportPortalConfig || window.YoOhwSupportConfig || { maxFiles: 5, maxSize: 5242880, messages: {} };
  }

  function message(key, fallback) {
    return (config().messages && config().messages[key]) || fallback;
  }

  function formatFileSize(bytes) {
    bytes = parseInt(bytes, 10) || 0;

    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + " KB";

    return (bytes / (1024 * 1024)).toFixed(bytes >= 10 * 1024 * 1024 ? 0 : 1) + " MB";
  }

  function renderSelectedFiles(input) {
    var target = document.querySelector('[data-upload-selected-for="' + input.id + '"]');

    if (!target) return;

    var files = Array.prototype.slice.call(input.files || []);
    target.replaceChildren();
    target.classList.toggle("is-empty", files.length === 0);

    if (!files.length) {
      target.textContent = target.getAttribute("data-empty-label") || message("noFilesSelected", "No files selected");
      return;
    }

    files.forEach(function (file) {
      var item = document.createElement("span");
      var name = document.createElement("span");
      var size = document.createElement("small");

      item.className = "yoohw-upload-file";
      name.textContent = file.name;
      size.textContent = formatFileSize(file.size);

      item.appendChild(name);
      item.appendChild(size);
      target.appendChild(item);
    });
  }

  function setupNavigation() {
    var toggle = document.querySelector(".yoohw-nav-toggle");
    var nav = document.getElementById("yoohw-support-nav");

    if (!toggle || !nav) return;

    toggle.addEventListener("click", function () {
      var isOpen = toggle.getAttribute("aria-expanded") === "true";
      toggle.setAttribute("aria-expanded", isOpen ? "false" : "true");
      nav.classList.toggle("is-open", !isOpen);
    });
  }

  function setupPasswordToggles() {
    document.querySelectorAll(".yoohw-password-toggle").forEach(function (button) {
      button.addEventListener("click", function () {
        var field = button.closest(".yoohw-password-field");
        var input = field ? field.querySelector("input") : null;
        var iconOn = button.querySelector("[data-icon-on]");
        var iconOff = button.querySelector("[data-icon-off]");

        if (!input) return;

        var show = input.type === "password";
        input.type = show ? "text" : "password";
        button.setAttribute("aria-label", show ? button.dataset.hideLabel : button.dataset.showLabel);

        if (iconOn) iconOn.hidden = show;
        if (iconOff) iconOff.hidden = !show;
      });
    });
  }

  function passwordStrengthLabels() {
    var wpLabels = window.pwsL10n || {};

    return {
      empty: message("passwordStrengthEmpty", wpLabels.empty || "Password strength"),
      short: message("passwordStrengthShort", wpLabels.short || "Very weak"),
      bad: message("passwordStrengthBad", wpLabels.bad || "Weak"),
      good: message("passwordStrengthGood", wpLabels.good || "Medium"),
      strong: message("passwordStrengthStrong", wpLabels.strong || "Strong"),
      mismatch: message("passwordStrengthMismatch", wpLabels.mismatch || "Mismatch")
    };
  }

  function passwordStrengthScore(password, input) {
    if (window.wp && window.wp.passwordStrength && window.wp.passwordStrength.meter) {
      var disallowed = [];

      if (window.wp.passwordStrength.userInputDisallowedList) {
        disallowed = window.wp.passwordStrength.userInputDisallowedList();
      }

      if (!Array.isArray(disallowed)) {
        disallowed = [];
      }

      if (input && input.dataset.userInput) {
        disallowed.push(input.dataset.userInput);
      }

      return window.wp.passwordStrength.meter(password, disallowed, "");
    }

    if (password.length >= 14) return 4;
    if (password.length >= 10) return 3;
    if (password.length >= 7) return 2;

    return 1;
  }

  function setupPasswordStrength() {
    document.querySelectorAll("[data-yoohw-password-strength]").forEach(function (input) {
      var form = input.closest("form");
      var result = form ? form.querySelector("[data-yoohw-password-strength-result]") : document.querySelector("[data-yoohw-password-strength-result]");
      var labels = passwordStrengthLabels();

      if (!result) return;

      function update() {
        var value = input.value || "";
        var className = "short";
        var label = labels.short;
        var score;

        if (!value) {
          result.className = "yoohw-password-strength is-empty";
          result.textContent = labels.empty;
          return;
        }

        score = passwordStrengthScore(value, input);

        if (score === 5) {
          className = "mismatch";
          label = labels.mismatch;
        } else if (score === 4) {
          className = "strong";
          label = labels.strong;
        } else if (score === 3) {
          className = "good";
          label = labels.good;
        } else if (score === 2) {
          className = "bad";
          label = labels.bad;
        }

        result.className = "yoohw-password-strength " + className;
        result.textContent = label;
      }

      input.addEventListener("input", update);
      input.addEventListener("keyup", update);
      input.addEventListener("change", update);
      update();
    });
  }

  function setupAttachmentValidation() {
    document.querySelectorAll('input[type="file"][data-max-files]').forEach(function (input) {
      var dropzone = document.querySelector('label[for="' + input.id + '"].yoohw-upload-dropzone');

      input.addEventListener("change", function () {
        var maxFiles = parseInt(input.getAttribute("data-max-files"), 10) || config().maxFiles || 5;
        var maxSize = parseInt(input.getAttribute("data-max-size"), 10) || config().maxSize || 5242880;
        var files = input.files || [];

        if (files.length > maxFiles) {
          alert(message("tooManyFiles", "You can upload a maximum of %d attachments.").replace("%d", maxFiles));
          input.value = "";
          renderSelectedFiles(input);
          return;
        }

        for (var i = 0; i < files.length; i++) {
          if (files[i].size > maxSize) {
            alert(message("fileTooLarge", "Each attachment must be 5 MB or smaller."));
            input.value = "";
            renderSelectedFiles(input);
            return;
          }
        }

        renderSelectedFiles(input);
      });

      if (!dropzone) return;

      ["dragenter", "dragover"].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          event.stopPropagation();
          dropzone.classList.add("is-dragover");
        });
      });

      ["dragleave", "drop"].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          event.stopPropagation();
          dropzone.classList.remove("is-dragover");
        });
      });

      dropzone.addEventListener("drop", function (event) {
        if (!event.dataTransfer || !event.dataTransfer.files.length) return;

        try {
          input.files = event.dataTransfer.files;
          input.dispatchEvent(new Event("change", { bubbles: true }));
        } catch (error) {
          input.click();
        }
      });
    });
  }

  function lockSubmit(form) {
    if (!form || form.dataset.yoohwSubmitting === "1") return false;

    form.dataset.yoohwSubmitting = "1";

    if (window.tinymce) {
      window.tinymce.triggerSave();
    }

    var submit = form.querySelector('button[type="submit"], input[type="submit"]');

    if (submit) {
      if ("value" in submit) {
        submit.value = message("submitting", "Submitting. Please wait...");
      } else {
        submit.textContent = message("submitting", "Submitting. Please wait...");
      }

      submit.disabled = true;
      submit.style.pointerEvents = "none";
      submit.style.opacity = "0.65";
    }

    return true;
  }

  function setupSubmitLocks() {
    document.querySelectorAll("form[data-yoohw-form], form#commentform").forEach(function (form) {
      if (form.id === "commentform") {
        form.noValidate = true;
        form.setAttribute("enctype", "multipart/form-data");
      }

      form.addEventListener("submit", function (event) {
        if (!lockSubmit(form)) {
          event.preventDefault();
        }
      });
    });
  }

  function enhanceCodeBlocks(root) {
    if (!root) return;

    root.querySelectorAll("pre").forEach(function (pre) {
      if (pre.closest(".yoohw-codeblock")) return;

      var wrapper = document.createElement("div");
      wrapper.className = "yoohw-codeblock";
      pre.parentNode.insertBefore(wrapper, pre);
      wrapper.appendChild(pre);

      var toolbar = document.createElement("div");
      toolbar.className = "yoohw-codeblock-toolbar";

      var label = document.createElement("span");
      label.className = "yoohw-codeblock-label";
      label.textContent = "code";

      var wrapButton = document.createElement("button");
      wrapButton.type = "button";
      wrapButton.className = "yoohw-codeblock-button";
      wrapButton.setAttribute("aria-pressed", "false");
      wrapButton.textContent = "Wrap";

      wrapButton.addEventListener("click", function () {
        var enabled = wrapper.classList.toggle("is-wrapped");
        wrapButton.setAttribute("aria-pressed", enabled ? "true" : "false");
      });

      var copyButton = document.createElement("button");
      copyButton.type = "button";
      copyButton.className = "yoohw-codeblock-button";
      copyButton.textContent = message("copy", "Copy");

      copyButton.addEventListener("click", function () {
        var text = pre.innerText || pre.textContent || "";

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function () {
            copyButton.textContent = message("copied", "Copied");
            window.setTimeout(function () {
              copyButton.textContent = message("copy", "Copy");
            }, 1200);
          });
          return;
        }

        var selection = window.getSelection();
        var range = document.createRange();
        range.selectNodeContents(pre);
        selection.removeAllRanges();
        selection.addRange(range);
      });

      toolbar.appendChild(label);
      toolbar.appendChild(wrapButton);
      toolbar.appendChild(copyButton);
      wrapper.insertBefore(toolbar, pre);
    });
  }

  function setupCodeBlocks() {
    document.querySelectorAll(".yoohw-topic-body, .yoohw-reply-content").forEach(enhanceCodeBlocks);
  }

  document.addEventListener("DOMContentLoaded", function () {
    setupNavigation();
    setupPasswordToggles();
    setupPasswordStrength();
    setupAttachmentValidation();
    setupSubmitLocks();
    setupCodeBlocks();
  });
})();
