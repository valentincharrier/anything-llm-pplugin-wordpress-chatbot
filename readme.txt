=== Ocade Fusion AnythingLLM Chatbot ===
Contributors: ocadefusion
Tags: chatbot, ai, anythingllm, chat, accessibility, gdpr
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fully accessible, GDPR-compliant AI chatbot powered by AnythingLLM for WordPress.

== Description ==

Ocade Fusion AnythingLLM Chatbot integrates a powerful AI assistant into your WordPress site, connecting to your AnythingLLM instance. Built with accessibility (RGAA AA compliant) and privacy (GDPR compliant) at its core.

= Key Features =

**AI-Powered Conversations**
* Connect to your AnythingLLM instance
* Real-time streaming responses (SSE)
* Support for multiple workspaces
* File upload capability
* Markdown rendering with syntax highlighting

**Accessibility (RGAA AA Compliant)**
* Skip link navigation
* Full keyboard support with focus trap
* ARIA live regions for screen readers
* Respects prefers-reduced-motion
* Minimum contrast ratios enforced

**Privacy & GDPR Compliance**
* Independent consent management
* Anonymized IP storage
* Configurable data retention
* Data export and deletion tools
* Integration with WordPress Privacy Tools

**Customization**
* Position in any corner
* Light/Dark theme support
* Custom colors and avatars
* Configurable messages
* Shortcode and Gutenberg block

**Performance**
* Lazy loading of assets
* Response caching
* Rate limiting
* Optimized bundle size (<300KB gzipped)

**Security**
* Nonce verification
* Input sanitization
* Honeypot spam protection
* WordPress coding standards

= Requirements =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* An AnythingLLM instance with API access

= Links =

* [Documentation](https://ocadefusion.com/docs/anythingllm-chatbot)
* [Support](https://ocadefusion.com/support)
* [GitHub Repository](https://github.com/ocade-fusion/anythingllm-chatbot)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ocade-fusion-anythingllm-chatbot/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Chatbot' > 'Settings' in your admin menu
4. Enter your AnythingLLM API URL, API Key, and Workspace Slug
5. Configure appearance, privacy, and accessibility settings
6. Save and the chatbot will appear on your site

= Using the Shortcode =

You can embed the chatbot in specific pages using the shortcode:

`[ofac_chatbot]`

With custom attributes:

`[ofac_chatbot title="Ask me anything" position="bottom-left"]`

= Using the Gutenberg Block =

1. Edit a page or post
2. Add a new block
3. Search for "AnythingLLM Chatbot"
4. Configure the block settings in the sidebar

== Frequently Asked Questions ==

= What is AnythingLLM? =

AnythingLLM is an open-source, all-in-one AI application that allows you to chat with your documents, use various LLM providers, and build AI-powered applications.

= Do I need my own AnythingLLM instance? =

Yes, this plugin connects to your own AnythingLLM instance. You need to have AnythingLLM installed and running, either self-hosted or using their cloud service.

= Is this plugin GDPR compliant? =

Yes, the plugin is designed with GDPR compliance in mind:
- User consent is requested before data collection
- IP addresses are anonymized
- Data retention periods are configurable
- Users can request export or deletion of their data
- Integrates with WordPress Privacy Tools

= Is the chatbot accessible? =

Yes, the plugin follows RGAA AA accessibility guidelines:
- Full keyboard navigation
- Screen reader support with ARIA attributes
- Focus management
- Respects reduced motion preferences
- High contrast support

= Can I customize the appearance? =

Yes, you can customize:
- Position (4 corners)
- Theme (light/dark/auto)
- Primary color
- Bot and user avatars
- Title and welcome message
- All text labels

= Does it support file uploads? =

Yes, users can upload files to the chatbot. You can configure:
- Maximum file size
- Allowed file types
- Enable/disable the feature

== Screenshots ==

1. Chatbot in action - Light theme
2. Chatbot in action - Dark theme
3. Admin settings - API configuration
4. Admin settings - Appearance customization
5. Admin settings - Privacy options
6. Statistics dashboard
7. Conversation logs
8. GDPR tools

== Changelog ==

= 1.0.0 =
* Initial release
* AnythingLLM integration
* SSE streaming support
* RGAA AA accessibility compliance
* GDPR compliance features
* Shortcode and Gutenberg block
* Admin dashboard with statistics
* Conversation logs management
* Import/export settings
* Multi-language support (EN/FR)

== Upgrade Notice ==

= 1.0.0 =
Initial release of Ocade Fusion AnythingLLM Chatbot.

== Privacy Policy ==

This plugin stores conversation data in your WordPress database. The data collected includes:

* Session identifiers (randomly generated)
* Anonymized IP addresses (last octet removed, then hashed)
* Conversation messages and timestamps
* User consent records

Data is automatically deleted after the configured retention period (default: 30 days).

No data is sent to third parties except your own AnythingLLM instance.

For more information, see our [Privacy Policy](https://ocadefusion.com/privacy).

== Credits ==

* Developed by [Ocade Fusion](https://ocadefusion.com)
* Icons by [Lucide](https://lucide.dev) (ISC License)
* Built with accessibility guidance from RGAA 4.1

== Support ==

For support, please visit our [support page](https://ocadefusion.com/support) or open an issue on [GitHub](https://github.com/ocade-fusion/anythingllm-chatbot/issues).
