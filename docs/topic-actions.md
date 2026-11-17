# Native topic action extension points

Approved companion task: YoOhw-com/yoohw-website#219. The source is the existing
native WordPress topic detail and approved comment render path. Add optional
rendering actions: `yoohw_support_topic_actions(WP_Post $topic)` and
`yoohw_support_reply_actions(WP_Comment $reply, WP_Post $topic)` after the
message bodies. The UI refinement adds
`yoohw_support_topic_header_actions(WP_Post $topic)` directly after the topic
title, as a child of the topic header, and
`yoohw_support_reply_header_actions(WP_Comment $reply, WP_Post $topic)` at the
end of each reply header. These generic header hooks let the site adapter render
a compact disclosure menu instead of a large standalone action button. Existing
body hooks remain available for inline confirmation and related-request content.
No wrapper or menu markup is added by Support itself.

The hooks run only inside the existing authorized native topic renderer. With no
listener there is no visible output or behavior change. No YFR dependency,
capability, query, recipient, persistence, storage migration or version change.
Isolated storage has no new hook in this bounded task; consumers must fail closed
when they cannot resolve that backend. Extensions remain responsible for their
own capability checks, output escaping and authorization on every POST. A
rendered action is not authorization to create or read another object.

The companion site integration extracts multiple requests from topics/replies
for the topic opener, not the selected reply author. It owns source resolution,
inline confirmation, nonce/idempotency and private source links. Core YFR owns
its generic privileged creation API. No conversation/attachments are copied
implicitly; no auth/session impersonation. Test hook invocation/identity with
synthetic native sources, unchanged authorization and no-listener rendering.
Independent source review and applicable CI required; no merge/release/deploy.
