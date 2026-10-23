(function () {
  "use strict";

  function setup(input) {
    // Keep native selection available when writable FileLists are unsupported.
    if (typeof DataTransfer === "undefined") return;
    var selected = Array.from(input.files || []);
    var labels = window.YoOhwAttachmentLabels || {};
    var target = document.querySelector('[data-upload-selected-for="' + input.id + '"]');
    var dropzone = document.querySelector('label.yoohw-upload-dropzone[for="' + input.id + '"]') || input.closest('[data-upload-dropzone]');
    if (!target) {
      target = document.createElement("div");
      target.className = "yoohw-upload-selected";
      target.id = input.id + "_selected";
      input.insertAdjacentElement("afterend", target);
      input.setAttribute("aria-describedby", ((input.getAttribute("aria-describedby") || "") + " " + target.id).trim());
    }
    target.setAttribute("aria-live", "polite");
    target.tabIndex = -1;

    function sync(files) {
      var transfer = new DataTransfer();
      files.forEach(function (file) { transfer.items.add(file); });
      input.files = transfer.files;
      selected = files;
    }

    function render() {
      target.replaceChildren();
      target.classList.toggle("is-empty", !selected.length);
      if (!selected.length) {
        target.textContent = target.getAttribute("data-empty-label") || labels.empty || "No files selected";
        return;
      }
      selected.forEach(function (file, index) {
        var item = document.createElement("span");
        var name = document.createElement("span");
        var size = document.createElement("small");
        var remove = document.createElement("button");
        item.className = "yoohw-upload-file";
        name.className = "yoohw-upload-file-name";
        name.textContent = file.name;
        size.textContent = file.size < 1024 ? file.size + " B" : file.size < 1048576 ? Math.round(file.size / 1024) + " KB" : (file.size / 1048576).toFixed(1) + " MB";
        remove.type = "button";
        remove.className = "yoohw-upload-remove";
        remove.textContent = "×";
        remove.setAttribute("aria-label", (labels.remove || "Remove %s").replace("%s", file.name));
        remove.title = remove.getAttribute("aria-label");
        remove.addEventListener("click", function () {
          sync(selected.filter(function (_, i) { return i !== index; }));
          render();
          var buttons = target.querySelectorAll("button");
          (buttons[Math.min(index, buttons.length - 1)] || target).focus();
        });
        item.append(name, size, remove);
        target.appendChild(item);
      });
    }

    function add(files) {
      var merged = selected.slice();
      Array.from(files).forEach(function (file) {
        if (!merged.some(function (old) { return old.name === file.name && old.size === file.size && old.lastModified === file.lastModified && old.type === file.type; })) merged.push(file);
      });
      var maxFiles = parseInt(input.getAttribute("data-max-files"), 10) || 5;
      var maxSize = parseInt(input.getAttribute("data-max-size"), 10) || 5242880;
      var error = "";
      if (merged.length > maxFiles) {
        error = (labels.tooMany || "You can upload a maximum of %d attachments.").replace("%d", maxFiles);
      } else if (merged.some(function (file) { return file.size > maxSize; })) {
        error = labels.tooLarge || "Each attachment must be 5 MB or smaller.";
      }
      if (error) {
        sync(selected);
        window.alert(error);
      } else {
        sync(merged);
      }
      render();
    }

    input.addEventListener("change", function () { add(input.files || []); });
    if (input.form) input.form.addEventListener("reset", function () { sync([]); render(); });
    if (dropzone) {
      ["dragenter", "dragover", "dragleave", "drop"].forEach(function (type) {
        dropzone.addEventListener(type, function (event) {
          event.preventDefault();
          event.stopPropagation();
          dropzone.classList.toggle("is-dragover", type === "dragenter" || type === "dragover");
          if (type === "drop" && event.dataTransfer) add(event.dataTransfer.files || []);
        });
      });
    }
    render();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('input[type="file"][name="yoohw_attachments[]"][data-max-files]').forEach(setup);
  });
})();
