(function () {
  "use strict";
  var labels = window.YoOhwComposerLabels || {};
  var allowed = "p br strong b em i u s ul ol li blockquote h3 h4 table thead tbody tfoot tr td th caption a pre code".split(" ");
  var attrs = { a: ["href", "title", "target", "rel"], ol: ["start", "reversed", "type"], li: ["value"], td: ["colspan", "rowspan"], th: ["colspan", "rowspan", "scope"] };
  function htmlEscape(value) {
    return (value || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  }
  function fromText(text) {
    text = (text || "").replace(/\r\n?/g, "\n");
    var result = "", last = 0;
    var fence = /(?:^|\n)[ \t]*(`{3,}|~{3,})[a-zA-Z0-9_+.#-]*[ \t]*\n([\s\S]*?)\n[ \t]*\1[ \t]*(?=\n|$)/g;
    function prose(value) { return value.split(/\n\s*\n/).map(function (part) { return part ? "<p>" + htmlEscape(part).replace(/\n/g, "<br>") + "</p>" : ""; }).join(""); }
    var match;
    while ((match = fence.exec(text))) {
      result += prose(text.slice(last, match.index)) + '<pre class="wp-code-block"><code>' + htmlEscape(match[2]) + "</code></pre>";
      last = fence.lastIndex;
    }
    return result + prose(text.slice(last));
  }
  function normalize(html) {
    // Template contents are inert: pasted image URLs must never be fetched during cleanup.
    var template = document.createElement("template");
    template.innerHTML = html;
    var removedMedia = false;
    function walk(parent) {
      // Word/Docs may split a fenced block into sibling paragraphs.
      var children = Array.prototype.slice.call(parent.childNodes);
      children.forEach(function (node, index) {
        if (node.parentNode !== parent || !/^(P|DIV)$/.test(node.nodeName)) return;
        var start = node.textContent.match(/^(`{3,}|~{3,})[a-zA-Z0-9_+.#-]*[ \t]*$/);
        if (!start) return;
        var end = index + 1;
        while (end < children.length && children[end].textContent !== start[1]) ++end;
        if (end === children.length) return;
        var lines = children.slice(index + 1, end).map(function (item) { return item.textContent; });
        var pre = document.createElement("pre"); pre.className = "wp-code-block"; var code = document.createElement("code"); code.textContent = lines.join("\n"); pre.appendChild(code);
        parent.insertBefore(pre, node); children.slice(index, end + 1).forEach(function (item) { item.remove(); });
      });
      Array.prototype.slice.call(parent.childNodes).forEach(function (node) {
        if (node.nodeType === 8) { node.remove(); return; }
        if (node.nodeType === 3) {
          var text = node.nodeValue;
          var parts = text.split(/(?<!`)`([^`\r\n]+)`(?!`)/);
          if (parts.length > 1) {
            var fragment = document.createDocumentFragment();
            parts.forEach(function (part, i) { var item = i % 2 ? document.createElement("code") : document.createTextNode(part.replace(/\u00a0/g, " ").replace(/[\t \f\v]+/g, " ")); if (i % 2) item.textContent = part; fragment.appendChild(item); });
            node.replaceWith(fragment);
          } else node.nodeValue = text.replace(/\u00a0/g, " ").replace(/[\t \f\v]+/g, " ");
          return;
        }
        if (node.nodeType !== 1) return;
        var tag = node.nodeName.toLowerCase();
        if (/^(img|iframe|object|embed|video|audio|svg|canvas)$/.test(tag)) { removedMedia = true; node.remove(); return; }
        if (/^(script|style|head|title|template|noscript|meta|link)$/.test(tag) || node.hasAttribute("hidden") || node.getAttribute("aria-hidden") === "true" || /(?:display\s*:\s*none|visibility\s*:\s*hidden)/i.test(node.getAttribute("style") || "")) { node.remove(); return; }
        if (tag === "pre" || tag === "code") {
          node.querySelectorAll("br").forEach(function (br) { br.replaceWith(document.createTextNode("\n")); });
          var text = node.textContent;
          Array.prototype.slice.call(node.attributes).forEach(function (a) { node.removeAttribute(a.name); });
          node.textContent = "";
          if (tag === "pre") { node.className = "wp-code-block"; var code = document.createElement("code"); code.textContent = text; node.appendChild(code); }
          else node.textContent = text;
          return;
        }
        walk(node);
        if (tag === "div") {
          if (node.querySelector("p,div,ul,ol,pre,blockquote,table,h3,h4")) { node.replaceWith.apply(node, Array.prototype.slice.call(node.childNodes)); return; }
          var paragraph = document.createElement("p"); while (node.firstChild) paragraph.appendChild(node.firstChild); node.replaceWith(paragraph); node = paragraph; tag = "p";
        }
        if (/^h[1-6]$/.test(tag)) { var heading = document.createElement(+tag[1] <= 3 ? "h3" : "h4"); while (node.firstChild) heading.appendChild(node.firstChild); node.replaceWith(heading); node = heading; tag = heading.nodeName.toLowerCase(); }
        if (allowed.indexOf(tag) < 0) { node.replaceWith.apply(node, Array.prototype.slice.call(node.childNodes)); return; }
        Array.prototype.slice.call(node.attributes).forEach(function (a) { if ((attrs[tag] || []).indexOf(a.name) < 0) node.removeAttribute(a.name); });
        if (tag === "a") {
          if (/^\s*(?:javascript|data|vbscript):/i.test(node.getAttribute("href") || "")) node.removeAttribute("href");
          if (node.getAttribute("target") === "_blank") node.setAttribute("rel", "noopener noreferrer");
        }
        if (/^(p|h3|h4|blockquote|strong|b|em|i|u|s)$/.test(tag) && !node.textContent.trim() && !node.querySelector("code,pre,table,ul,ol")) node.remove();
      });
      var breaks = 0;
      Array.prototype.slice.call(parent.childNodes).forEach(function (node) {
        if (node.nodeName === "BR") { if (++breaks > 2) node.remove(); }
        else if (node.nodeType !== 3 || node.nodeValue.trim()) breaks = 0;
      });
    }
    walk(template.content);
    return { html: template.innerHTML.trim(), removedMedia: removedMedia };
  }
  function setup(editor) {
    function inPre() { return editor.dom.getParent(editor.selection.getStart(), "pre"); }
    editor.on("init", function () { editor.formatter.register("yoohw_inline_code", { inline: "code" }); });
    function togglePre() {
      editor.undoManager.transact(function () {
        var pre = inPre();
        if (pre) {
          var p = editor.dom.create("p", {}, htmlEscape(pre.textContent).replace(/\n/g, "<br>"));
          editor.dom.replace(p, pre); editor.selection.setCursorLocation(p, 0); return;
        }
        var content = editor.selection.getContent({ format: "text" });
        var block = editor.dom.create("pre", { "class": "wp-code-block" });
        block.textContent = content;
        if (!content && editor.selection.isCollapsed()) {
          var node = editor.dom.getParent(editor.selection.getStart(), "p");
          if (node) { block.textContent = node.textContent; editor.dom.replace(block, node); editor.selection.setCursorLocation(block, 0); return; }
        }
        editor.insertContent('<pre class="wp-code-block"><code>' + htmlEscape(content || "\n") + "</code></pre>");
      });
    }
    function warnMedia() {
      var message = labels.removedMedia || "Pasted media was removed. Use Attachments where available.";
      if (editor.notificationManager) editor.notificationManager.open({ text: message, type: "warning" });
      else editor.windowManager.alert(message);
    }
    editor.on("paste", function (event) {
      var clipboard = event.clipboardData || window.clipboardData;
      if (!clipboard || !clipboard.getData) return;
      var text = clipboard.getData("text/plain") || clipboard.getData("Text") || "";
      var html = clipboard.getData("text/html") || "";
      var files = clipboard.files && clipboard.files.length;
      if (!text && !html) { if (files) { event.preventDefault(); warnMedia(); } return; }
      event.preventDefault();
      if (editor.dom.getParent(editor.selection.getStart(), "pre,code")) {
        editor.undoManager.transact(function () { editor.insertContent(htmlEscape(text.replace(/\r\n?/g, "\n"))); }); return;
      }
      var result = normalize(html || fromText(text));
      editor.undoManager.transact(function () { editor.insertContent(result.html); });
      if (result.removedMedia || files) warnMedia();
    });
    var enterCount = 0;
    var enterTimer = null;

    editor.on("keydown", function (event) {
      var key = event.key || event.keyCode;
      var isEnter = key === "Enter" || key === 13;
      var pre = inPre();

      if (!pre) {
        if (enterTimer) window.clearTimeout(enterTimer);
        enterCount = 0;
        enterTimer = null;
        return;
      }

      if (!isEnter) {
        if (event.keyCode !== 16) {
          if (enterTimer) window.clearTimeout(enterTimer);
          enterCount = 0;
          enterTimer = null;
        }
        return;
      }

      if (event.shiftKey) {
        enterCount = 0;
        return;
      }

      enterCount++;

      if (enterTimer) window.clearTimeout(enterTimer);
      enterTimer = window.setTimeout(function () {
        enterCount = 0;
        enterTimer = null;
      }, 700);

      if (enterCount < 2) return;

      event.preventDefault();
      editor.undoManager.transact(function () {
        var text = pre.textContent || "";

        if (/\n$/.test(text)) {
          pre.textContent = text.replace(/\n$/, "");
        }

        var paragraph = editor.dom.create("p", {}, "");

        if (pre.nextSibling) {
          pre.parentNode.insertBefore(paragraph, pre.nextSibling);
        } else {
          pre.parentNode.appendChild(paragraph);
        }

        if (!(pre.textContent || "").trim()) {
          pre.parentNode.removeChild(pre);
        }

        editor.selection.setCursorLocation(paragraph, 0);
      });

      enterCount = 0;
      if (enterTimer) window.clearTimeout(enterTimer);
      enterTimer = null;
    });


    function button(name, label, action, selector, icon) {
      if (editor.addButton) editor.addButton(name, { icon: icon, tooltip: label, onclick: action, onpostrender: function () { var control = this; control.getEl().classList.add("yoohw-composer-code-tool"); editor.on("NodeChange", function () { control.active(!!editor.dom.getParent(editor.selection.getStart(), selector)); }); } });
      else if (editor.ui && editor.ui.registry) editor.ui.registry.addButton(name, { icon: icon === "code" ? "sourcecode" : icon, tooltip: label, onAction: action });
    }
    button("yoohw_support_codeblock", labels.codeBlock || "Code block", togglePre, "pre", "codesample");
    button("yoohw_support_inlinecode", labels.inlineCode || "Inline code", function () { if (!inPre()) editor.formatter.toggle("yoohw_inline_code"); }, "code", "code");
  }
  window.YoOhwSupportEditor = { setup: setup, normalize: normalize, fromText: fromText };
})();
