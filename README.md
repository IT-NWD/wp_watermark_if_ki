# WP KI-Badge Plugin

WordPress-Plugin zur manuellen und optional automatischen Kennzeichnung von KI-generierten Bildern.

## Funktionen

- Checkbox „Als KI-generiertes Bild kennzeichnen“ direkt im Medien-Manager.
- Zusätzliche Spalte „KI-Bild“ in der Medienübersicht.
- Optionale automatische Erkennung beim Hochladen und Bearbeiten eines Bildes.
- Prüfung von WordPress-Bildmetadaten sowie eingebetteten XMP-Daten.
- Konfigurierbare Suchbegriffe, ein Begriff pro Zeile.
- Optionales Frontend-Badge auf gekennzeichneten Bildern.
- Responsive Badge-Positionierung ohne Herauslaufen auf kleinen Bildschirmen.
- Shortcode für eindeutig zugeordnete Hintergrundbilder: `[wki_ai_background image_id="123"]`.
- Kleines EU-`AI`-SVG-Logo im Badge; die Kennzeichnungsart bleibt im Medien-Manager auswählbar.
- AI-Logo im Badge optional ein- und ausschaltbar.
- Kompakte Box mit einstellbarem Innenabstand und einstellbarer Transparenz.
- Schriftfamilie, Schriftstärke und Schriftfarbe des Badges konfigurierbar.
- Avada-Live-Editor-Erkennung: Im Builder werden keine Badge-Markups oder Output-Buffer-Manipulationen ausgeführt.
- Allgemeines EU-`AI`-Symbol mit optionalem Wechsel zum gewählten Typ-Symbol bei Mouseover.
- Reguläre Alt-Texte werden beim manuellen Kennzeichnen automatisch ergänzt, wenn noch kein eigener Alt-Text vorhanden ist.
- Vergrößerbare Hover-Darstellung des EU-Logos mit eigener Größeneinstellung.
- Auswahl der vier offiziellen Icon-Varianten: schwarz, weiß und jeweils transparent.
- Erfassung regulärer WordPress-Bilder und direkt von Avada ausgegebener Image-Elemente über die Attachment-Klasse.
- Erfassung von Avada-Container-Hintergründen über `data-bg` und `data-bg-url`.
- Manuelle Kennzeichnungen haben Vorrang und werden von der automatischen Erkennung nicht überschrieben.

## Installation

1. Plugin-ZIP unter **Plugins → Installieren → Plugin hochladen** installieren.
2. Plugin aktivieren.
3. Unter **KI-Badge** im WordPress-Hauptmenü automatische Erkennung und Frontend-Badge konfigurieren.
4. Ein Bild in der Mediathek öffnen und die Checkbox **Als KI-generiertes Bild kennzeichnen** setzen.

Für ein als Hintergrund gestaltetes Bild kann der Shortcode verwendet werden. Die Bild-ID muss dabei zu einem als KI-Bild markierten Medienobjekt gehören:

`[wki_ai_background image_id="123" height="320px"]`

Der optionale Parameter `class` kann eine zusätzliche CSS-Klasse für den Container setzen. Das Badge bleibt an den Container gebunden und wird auf kleinen Bildschirmen in Breite und Schriftgröße begrenzt.

Die Metadatenerkennung ist eine unterstützende Heuristik. Fehlen entsprechende EXIF-, IPTC- oder XMP-Hinweise, kann ein Bild nicht zuverlässig automatisch als KI-generiert erkannt werden.

Im Medien-Manager kann zusätzlich zwischen **vollständig KI-generiert**, **teilweise KI-modifiziert** und **grundlegendem KI-Symbol** gewählt werden. Das Plugin verwendet dafür die offiziellen, frei verfügbaren EU-SVG-Symbole und ergänzt sie um eine einfache Textbeschriftung sowie ein ARIA-Label.

Die EU-Symbole sind ein technisches Kennzeichnungsmittel und stellen allein keine rechtliche Konformität nach dem KI-Gesetz her. Maßgeblich sind unter anderem Inhaltstyp, Veröffentlichungskontext und die jeweils geltenden Transparenzpflichten.

Die Plugin-Einstellungen sind im WordPress-Hauptmenü unter **KI-Badge** erreichbar. Das Frontend-Badge ist bei neuen Installationen standardmäßig aktiv und kann dort angepasst oder deaktiviert werden.

## Technische Hinweise

Die Kennzeichnung wird als Attachment-Metadaten gespeichert:

- `_wki_is_ai`: `1` oder `0`
- `_wki_ai_source`: `manual` oder `metadata`
- `_wki_ai_matches`: gefundene Suchbegriffe

Die Ausgabe erfolgt nur optional über das Frontend-Badge; eine automatische Einbindung in Theme-Menüs oder Footer findet nicht statt.
