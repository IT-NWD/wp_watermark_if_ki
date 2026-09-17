# KI-Bildkennzeichnung

WordPress-Plugin zur manuellen und optional automatischen Kennzeichnung von KI-generierten Bildern.

## Funktionen

- Checkbox „Als KI-generiertes Bild kennzeichnen“ direkt im Medien-Manager.
- Zusätzliche Spalte „KI-Bild“ in der Medienübersicht.
- Optionale automatische Erkennung beim Hochladen und Bearbeiten eines Bildes.
- Prüfung von WordPress-Bildmetadaten sowie eingebetteten XMP-Daten.
- Konfigurierbare Suchbegriffe, ein Begriff pro Zeile.
- Optionales Frontend-Badge auf gekennzeichneten Bildern.
- Manuelle Kennzeichnungen haben Vorrang und werden von der automatischen Erkennung nicht überschrieben.

## Installation

1. Plugin-ZIP unter **Plugins → Installieren → Plugin hochladen** installieren.
2. Plugin aktivieren.
3. Unter **Einstellungen → KI-Bildkennzeichnung** automatische Erkennung und Frontend-Badge konfigurieren.
4. Ein Bild in der Mediathek öffnen und die Checkbox **Als KI-generiertes Bild kennzeichnen** setzen.

Die Metadatenerkennung ist eine unterstützende Heuristik. Fehlen entsprechende EXIF-, IPTC- oder XMP-Hinweise, kann ein Bild nicht zuverlässig automatisch als KI-generiert erkannt werden.

## Technische Hinweise

Die Kennzeichnung wird als Attachment-Metadaten gespeichert:

- `_wki_is_ai`: `1` oder `0`
- `_wki_ai_source`: `manual` oder `metadata`
- `_wki_ai_matches`: gefundene Suchbegriffe

Die Ausgabe erfolgt nur optional über das Frontend-Badge; eine automatische Einbindung in Theme-Menüs oder Footer findet nicht statt.
