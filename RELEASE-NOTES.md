# Stud.IP v6.2

**16.11.2026**

## Neue Features

System:
- Full Calendar wurde auf Version 6 aktualisiert
- In Ankündigungen ist Mediensupport zur Einbindung von Videos enthalten
- Deeplinks und Short-URLs lassen sich auf jeder Seite aus dem Footer aufrufen
- Zielgruppenspezifische Startseitenwidgets inkl. Push-Option
- Root: Rootassistent erlaubt direkten Zugriff auf Konfiguratiosoptionen nach einem Update

Raumverwaltung:
- Teilbare Räume lassen sich verwalten
- Einem Veranstaltungstermin lässt sich nun mehr als ein Raum zuordnen
- Feiertage werden auch in den Raumbelegungsplänen angezeigt

Veranstaltungen:
- RTF-Export wurde neu implementiert
- Anlage von Ordnern und Foren zu Themen wurde vereinfacht und per Sammelaktion durchführbar

## Breaking changes

- Es ist nun möglich, mehrere Räume für einen Veranstaltungstermin zu buchen (TIC #639). Das Kernsystem ist darauf komplett umgestellt.
 Das bedeutet aber: Alle Plugins und alle von einem Standort selbst veränderten Stellen im Kernsystem, die über einen Veranstaltungstermin bisher auf Raumbuchungen zugegriffen haben, müssen angepasst werden. Zudem wurde die Relation room_booking in der CourseDate-Klasse entfernt und durch die Relation room_bookings ersetzt. Die Methoden getRoomName und getRoom sind aus Gründen der Rückwärtskompatibilität noch enthalten, liefern aber nur Daten zu genau einem für den Veranstaltungstermin gebuchten Raum. Sind mehrere Räume gebucht, wird der zweite und alle weiteren nicht übertragen. Bitte beachten Sie, dass das je nach Kontext unvollständige Ergebnisse sein können!


## Security related issues

- fixed: Sicherheitslücke beim Löschen von Nachrichtenanhängen (#7032). Thanks to kta1kri.
- fixed: Hash des Zugangspassworts für Veranstaltungen ist über die JSON-API sichtbar (#7033). Thanks to kta1kri.
- fixed: Sicherheitslücke beim Löschen von Dokumenten in MVV (#7086). Thanks to Elmar Ludwig. 

## Deprecated Features

- Die externe Bibliothek phpass zum hashen von Benutzerpasswörtern wird in absehbarer Zeit durch die PHP-eigene Implementation von bcrypt ersetzt. Die bisher verfügbare Option PHPASS_USE_PORTABLE_HASH wird damit obsolet, damit erzeugte Passwort-Hashes sind nicht kompatibel (siehe #4131). Falls sie unter Admin -> System -> Konfiguration die Option PHPASS_USE_PORTABLE_HASH aktiviert hatten dann deaktivieren Sie diese bitte und melden sich bei uns.

## Known Issues

- Der Courseware-Import funktioniert nicht über alle Versionen hinweg zufriedenstellend
