=== KI-Badge ===
Tags: artificial intelligence, ai, media, accessibility, badge
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Kennzeichnet KI-generierte und KI-modifizierte Bilder in der Mediathek und auf der Website mit den offiziellen EU-Symbolen.

== Description ==

KI-Badge ergänzt die WordPress-Mediathek um eine manuelle und optionale metadatenbasierte Kennzeichnung von KI-Bildern.

Funktionen:

* Kennzeichnung und Inhaltstyp direkt am Medienobjekt
* Offizielle EU-Symbole für KI-generierte und KI-modifizierte Inhalte
* Anpassbare Position, Größe, Hovergröße, Schrift und Farben
* Automatische Auswahl eines schwarzen oder weißen Logos anhand des Bildkontrasts
* Manueller Button zum Ergänzen der KI-Kennzeichnung im vorhandenen Alt-Text
* Unterstützung regulärer Bilder und Avada-Hintergrundbilder
* Abschaltung der Frontend-Verarbeitung im Avada-Live-Editor

Die Bildanalyse läuft lokal über Imagick oder GD. Es werden keine Bilddaten an externe Dienste übertragen.

== Installation ==

1. Den Ordner `ki-badge` nach `/wp-content/plugins/` hochladen oder das ZIP über die Plugin-Verwaltung installieren.
2. KI-Badge aktivieren.
3. Die Darstellung unter „KI-Badge“ konfigurieren.
4. Bilder in der Mediathek als KI-Bild markieren und den passenden Inhaltstyp wählen.

== Frequently Asked Questions ==

= Werden vorhandene Alt-Texte überschrieben? =

Nein. Die automatische Kennzeichnung füllt nur leere Alt-Texte. Über den manuellen Button kann die KI-Kennzeichnung hinter eine bestehende Beschreibung gesetzt oder aktualisiert werden.

= Benötigt die automatische Logo-Farbe einen externen Dienst? =

Nein. Die Kontrastanalyse erfolgt vollständig lokal mit Imagick oder GD.

== Changelog ==

= 0.6.2 =

* WordPress.org-konformer Plugin-Name und Paket-Slug.
* Standardisierte readme.txt ergänzt.
* Request-Erkennung ohne direkte ungesicherte Superglobal-Zugriffe umgesetzt.

= 0.6.1 =

* Manueller Button zum Ergänzen und Aktualisieren der KI-Kennzeichnung in Alt-Texten.

= 0.6.0 =

* Lokale automatische Kontrasterkennung für schwarze und weiße Logo-Varianten ergänzt.
