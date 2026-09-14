(function () {
  function htmlEscape(value) {
    return (value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function looksLikeCode(text, html) {
    text = text || "";
    html = html || "";

    var multiLine = /\n/.test(text);
    var indented = /^(?:\t| {2,})\S/m.test(text);
    var braces = /[{};]/.test(text);
    var fnLike = /^[\t ]*(?:function|class|if|for|while|switch|return|const|let|var|public|private|protected)\b/m.test(text);
    var php = /^[\t ]*<\?php/m.test(text);
    var codeHtml = /<(pre|code)/i.test(html);

    return codeHtml || ((multiLine || indented) && (braces || fnLike || php));
  }

  function setup(editor) {
    function inPre() {
      return editor.dom.getParent(editor.selection.getStart(), "pre");
    }

    function togglePre() {
      editor.undoManager.transact(function () {
        var pre = inPre();

        if (pre) {
          var html = pre.textContent.replace(/\n/g, "<br>");
          var paragraph = editor.dom.create("p", {}, html);
          editor.dom.replace(paragraph, pre);
          editor.selection.setCursorLocation(paragraph, 0);
          return;
        }

        var content = editor.selection.getContent({ format: "text" });

        if (!content) {
          var node = editor.selection.getStart();
          content = node && node.textContent ? node.textContent : "";
        }

        editor.insertContent('<pre class="wp-code-block">' + htmlEscape(content || "\n") + "</pre>");
      });
    }

    function setActive(api) {
      function update() {
        api.setActive(!!inPre());
      }

      editor.on("NodeChange", update);
      return function () {
        editor.off("NodeChange", update);
      };
    }

    editor.on("paste", function (event) {
      var html = "";
      var text = "";

      if (event.clipboardData && event.clipboardData.getData) {
        html = event.clipboardData.getData("text/html") || "";
        text = event.clipboardData.getData("text/plain") || "";
      } else if (window.clipboardData && window.clipboardData.getData) {
        text = window.clipboardData.getData("Text") || "";
      }

      if (inPre()) {
        event.preventDefault();
        editor.insertContent(htmlEscape((text || "").replace(/\r\n?/g, "\n")));
        return;
      }

      var hasRichTags = /<(ul|ol|li|b|strong|i|em|a|p|br|h[1-6]|table|tr|td|th|blockquote)/i.test(html);

      if (looksLikeCode(text, html) && !hasRichTags) {
        event.preventDefault();
        editor.insertContent('<pre class="wp-code-block">' + htmlEscape((text || "").replace(/\r\n?/g, "\n")) + "</pre>");
      }
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

    if (editor.ui && editor.ui.registry && editor.ui.registry.addToggleButton) {
      editor.ui.registry.addToggleButton("yoohw_support_codeblock", {
        tooltip: "Code block",
        text: "</>",
        onAction: togglePre,
        onSetup: setActive,
      });
    } else if (editor.addButton) {
      editor.addButton("yoohw_support_codeblock", {
        text: "</>",
        tooltip: "Code block",
        onclick: togglePre,
        onpostrender: function () {
          var control = this;
          editor.on("NodeChange", function () {
            control.active(!!inPre());
          });
        },
      });
    }
  }

  window.YoOhwSupportEditor = {
    setup: setup,
  };
})();
