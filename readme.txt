=== KI-Badge ===
Tags: artificial intelligence, ai, media, accessibility, badge
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Labels AI-generated and AI-modified media library images on the website with the official EU symbols.

== Description ==

KI-Badge adds manual and optional metadata-based AI image labeling to the WordPress media library.

Features:

* Label and content type controls on media attachments
* Official EU symbols for AI-generated and AI-modified content
* Customizable position, size, hover size, typography, and colors
* Automatic black or white logo selection based on local image contrast
* Manual button for appending the AI disclosure to existing alternative text
* Support for regular images and Avada background images
* Frontend processing bypass in the Avada Live Editor

Image analysis runs locally through Imagick or GD. No image data is sent to external services.

== Installation ==

1. Upload the `ki-badge` directory to `/wp-content/plugins/`, or install the ZIP through the plugin screen.
2. Activate KI-Badge.
3. Configure the presentation under “KI-Badge”.
4. Mark images in the media library as AI images and select the appropriate content type.

== Frequently Asked Questions ==

= Does the plugin overwrite existing alternative text? =

No. Automatic labeling only fills empty alternative text. The manual button can append or update the AI disclosure after an existing description.

= Does automatic logo color selection require an external service? =

No. Contrast analysis runs entirely on the local server through Imagick or GD.

== Changelog ==

= 0.6.3 =

* Updated the plugin directory readme to the required Standard English format.

= 0.6.2 =

* Added a WordPress.org-compliant plugin name and package slug.
* Added the standard plugin directory readme file.
* Reworked request detection to avoid direct unsecured superglobal access.

= 0.6.1 =

* Added a manual button for appending and updating AI disclosures in alternative text.

= 0.6.0 =

* Added local automatic contrast detection for black and white logo variants.
