(function (wp) {
  if (!wp || !wp.plugins || !wp.element || !wp.data || !wp.editPost) {
    return;
  }

  var cfg = window.YoOhwSupportResolvedStatus || {};
  cfg.status = cfg.status || "resolved";
  cfg.label = cfg.label || "Resolved";
  cfg.description = cfg.description || "Support topic is resolved and visible.";
  cfg.actionLabel = cfg.actionLabel || "Mark as resolved";
  cfg.reopenLabel = cfg.reopenLabel || "Reopen";
  cfg.pendingLabel = cfg.pendingLabel || "Status will be saved when you update the post.";
  cfg.openLabel = cfg.openLabel || "Open";

  var registerPlugin = wp.plugins.registerPlugin;
  var createElement = wp.element.createElement;
  var useSelect = wp.data.useSelect;
  var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
  var Button = wp.components && wp.components.Button;

  if (!PluginPostStatusInfo || !Button || !useSelect) {
    return;
  }

  function setEditorStatus(status) {
    var dispatcher = wp.data.dispatch("core/editor");

    if (dispatcher && dispatcher.editPost) {
      dispatcher.editPost({ status: status });
    }
  }

  function StatusIcon(props) {
    return createElement("span", {
      className:
        "dashicons " +
        (props.resolved ? "dashicons-yes-alt" : "dashicons-admin-comments") +
        " yo-resolved-editor-status__icon",
      "aria-hidden": "true",
    });
  }

  function ResolvedStatusIntegration() {
    var editorState = useSelect(function (select) {
      var editor = select("core/editor");

      return {
        postType:
          editor && editor.getCurrentPostType ? editor.getCurrentPostType() : "",
        status:
          editor && editor.getEditedPostAttribute
            ? editor.getEditedPostAttribute("status")
            : "",
        savedStatus:
          editor && editor.getCurrentPostAttribute
            ? editor.getCurrentPostAttribute("status")
            : "",
        isSaving:
          editor && editor.isSavingPost ? editor.isSavingPost() : false,
      };
    }, []);

    if (!editorState || editorState.postType !== "post") {
      return null;
    }

    var isResolved = editorState.status === cfg.status;
    var hasPendingStatusChange = editorState.status !== editorState.savedStatus;
    var statusLabel = isResolved ? cfg.label : cfg.openLabel;
    var buttonLabel = isResolved ? cfg.reopenLabel : cfg.actionLabel;
    var nextStatus = isResolved ? "publish" : cfg.status;

    return createElement(
      PluginPostStatusInfo,
      { className: "yo-resolved-editor-status" },
      createElement(
        "div",
        { className: "yo-resolved-editor-status__body" },
        createElement(
          "div",
          { className: "yo-resolved-editor-status__summary" },
          createElement(StatusIcon, { resolved: isResolved }),
          createElement(
            "span",
            { className: "yo-resolved-editor-status__label" },
            statusLabel
          )
        ),
        createElement(
          Button,
          {
            variant: "secondary",
            size: "compact",
            disabled: editorState.isSaving,
            onClick: function () {
              setEditorStatus(nextStatus);
            },
          },
          buttonLabel
        )
      ),
      hasPendingStatusChange &&
        createElement(
          "p",
          { className: "yo-resolved-editor-status__note" },
          cfg.pendingLabel
        )
    );
  }

  registerPlugin("yoohw-support-post-status-resolved", {
    render: ResolvedStatusIntegration,
  });
})(window.wp);
