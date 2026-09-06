=== WP Edit With AI ===
Contributors: yourname
Tags: ai, chat, editing, content, assistant
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 0.4.0
License: GPLv2 or later

Chat-driven content editing for non-technical WordPress clients. No MCP connector, no external app, no subscription required — just a chat box that makes the edit directly.

== Description ==

Lets non-technical clients update their own WordPress content (schedules, headlines, copy, hours) by describing the change in plain language, instead of learning the block editor or a page builder.

Settings/dashboard: **WP Admin > WP Edit With AI**.

Content types supported, in build order:
1. Plain HTML / Gutenberg blocks
2. ACF fields and repeaters
3. Elementor widgets (`_elementor_data`)

== Changelog ==

= 0.4.0 =
* Removed the separate Settings submenu/page. Settings now render inline on the main WP Edit With AI page (visible only to users with manage_options), so there is only one registered menu item and one URL.
* Fix: on at least one live site, the Settings submenu link rendered as a broken/mismatched URL (likely a conflict with a menu-customization plugin) causing "not found". Collapsing to a single page removes the second menu registration entirely.

= 0.3.0 =
* Added the actual tool-calling loop: Gemini can now call find_pages, get_page_content, and update_page_content against the live database, looping until it gives a final reply.
* Chat UI now shows which tool calls ran and their result (success/error) for each message, so edits are visible/verifiable, not just the AI's text reply.

= 0.2.0 =
* Added Gemini API integration (generateContent, model configurable, defaults to gemini-flash-latest).
* Added Settings page (WP Admin > WP Edit With AI > Settings) for API keys and model.
* Added automatic key rotation (falls back to key 2 on auth/quota errors) for free-tier usage.
* Added "Test API Connection" check on the Settings page.
* Chat endpoint now sends messages to Gemini instead of returning a placeholder.

= 0.1.0 =
* Initial scaffold: admin chat UI, REST endpoint stub, tool-execution class stub.
