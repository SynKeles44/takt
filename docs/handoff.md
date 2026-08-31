# Übergabe — Stand 28.08.2026

`agent-config session:recycle` schlägt fehl (`envelope is not valid JSON: SyntaxError:
Unexpected end of JSON input`), deshalb diese Datei von Hand. Sie ist absichtlich nicht
committed — sie gehört der laufenden Arbeit, nicht dem Repository.

## Wo der Code steht

508 Tests grün, Pint clean, Swift kompiliert (`swiftc -swift-version 5 desktop/main.swift`).
**7 Commits liegen lokal und sind nicht gepusht**, und sie waren für ihre Runde nicht
autorisiert — der Nutzer entscheidet, ob gepusht oder anders geschnitten wird:

```
4371995 docs: concepts for the ticket board and the pull request area
bc0008f perf: scrolling stops paying for things nobody can see
1691565 feat: the Mac's calendar and an activity trail
06745a6 feat: the Mac going away, and Takt on the LAN
3cb839f feat: menu bar item, global shortcut, and the 400 Linear was sending
daa389f feat: a ticket area that pulls from Linear
3055334 feat: wait figures, book-like-last-time, releases, palette
```

## Was fertig ist

Phasen 54–61 aus `docs/plan.md` (alle abgehakt dort nachlesbar). Kurz:

- PR-Wartezahlen, „Wie letztes Mal" buchen, Releases-Bereich, Palette sieht alle Quellen
- Ticket-Bereich: Linear als Quelle (`Linear::mine`), Git als Anreicherung (`Tickets`)
- Menüleiste + ⌥⌘T (`desktop/main.swift`, über `window.takt` in `resources/js/app.js`)
- „Der Mac war weg" (`AwayTime`), Kalender lesen (`CalendarEvents`), LAN-Zugriff
  (`NetworkAccess`, Schalter ist die Datei `storage/app/network-access`), Tätigkeitsspur
  (`ActivityTrail`)
- Scroll-Performance: fixierte Gradient-Ebene, Blur nur im Edit-Modus, `contain`/
  `content-visibility`; Backdrop-Filter 27 → 5

## Was offen ist

1. **UI-Durchgang mit Animationen** (Wunsch des Nutzers) — bewusst nicht begonnen, weil das
   Ticket-Board-Konzept die Oberfläche dort ersetzt.
2. **Ticket-Board** nach `docs/concepts/tickets-board.md` — 6 Phasen, Nutzer wählt.
3. **PR-Bereich** nach `docs/concepts/pull-requests.md` — 6 Phasen, ersetzt Releases.
4. Kein QR-Code für den LAN-Zugriff (bräuchte eine dritte Abhängigkeit).
5. Kurzbefehl fest auf ⌥⌘T (einstellbar bräuchte ein Recorder-Feld).
6. Kalendertermine erscheinen im Widget, noch nicht im Verlauf.

## Warnungen für die Nachfolge

- **Nie auf der echten Datenbank rendern.** Ein Vorschau-Skript hat das Dashboard-Layout des
  Nutzers überschrieben; es war nicht wiederherstellbar. Das Layout liegt jetzt im
  Backup-Export (`Backup::export`), aber die Lehre bleibt.
- Die App-Fenster-Overrides in `resources/css/app.css` müssen am **Dateiende** stehen,
  außerhalb jedes Layers — `StylesheetTest` prüft das.
- Ein Widget rendert **genau ein** Wurzelelement (`.widget-body > *` streckt jedes auf volle
  Kachelhöhe). Zwei Wurzeln haben schon zweimal den Timer verschluckt.
- Nichts Serialisiertes in den Cache: `serializable_classes => false`, Carbon kommt als
  `__PHP_Incomplete_Class` zurück. Immer ISO-Strings.
- Linear nimmt den Schlüssel **roh** im `Authorization`-Header (kein „Bearer"), und eine
  Abfrage ohne Variablen darf das Feld `variables` nicht senden.
