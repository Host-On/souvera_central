# Souvera User Management - Nextcloud App

## Worum geht's?

Diese Custom Nextcloud App ersetzt die Standard-Benutzerverwaltung durch eine moderne, benutzerfreundliche Lösung mit Echtzeit-Validierung und Lizenz-Management.

### Problem
Die Standard-Nextcloud-Benutzerverwaltung ist:
- Tabellenbasiert und unübersichtlich
- Bietet keine Domain-Validierung
- Keine Live-Lizenzanzeige
- Keine proaktiven Fehlermeldungen

### Lösung
Moderne Single-Page-Editor-App mit:
- **Übersichtlicher UI**: Sidebar mit User-Liste + Hauptbereich für Bearbeitung
- **Live-Validierung**: Echtzeit-Domain-Checks während der Eingabe
- **Lizenz-Tracking**: Permanente Anzeige "X von Y Lizenzen genutzt"
- **Proaktive Fehler**: Sofortige Rückmeldung bei Problemen
- **API-Integration**: Externe Domain- und Lizenz-APIs für Validierung

---

## Feature-Set

### Kern-Features
- ✅ User erstellen, bearbeiten, löschen
- ✅ Echtzeit-Domain-Validierung gegen Nextcloud Config
- ✅ Live-Lizenz-Status-Anzeige (mit Warnung bei 80%+)
- ✅ Single-Page-Editor statt Tabellen-Interface
- ✅ Proaktive Fehlermeldungen inline im Formular
- ✅ Responsive Design (Desktop, Tablet, Mobile)

### Erweiterte Features
- ✅ Deutsche Lokalisierung
- ✅ Modulares Design für zukünftige Erweiterungen

---

## Entwicklungsprinzipien

### DRY (Don't Repeat Yourself)
- Wiederverwendbare Vue-Komponenten
- Gemeinsame Error-Handling-Logic
- Keine duplizierten Validierungen

### Modularität
- Klare Trennung: Frontend ↔ Backend
- Austauschbare Komponenten
- Service-orientierte Architektur
- Lose Kopplung zwischen Modulen

### Erweiterbarkeit
- Plugin-ready Architektur
- Event-basierte Kommunikation
- Konfigurierbare Config-Variablen
- Vorbereitet für zusätzliche Account-Management-Features

### Ordentliche Dokumentation (Deutsch)
- Code-Kommentare auf Deutsch
- README mit Setup-Anleitung
- Dokumentation für alle Config-Variablen
- Inline-Docs für alle Services und Komponenten
- Beispiele für häufige Use-Cases

---

## Tech-Stack

**Backend:** PHP (Nextcloud App Framework)
**Frontend:** Vue.js 3 + TypeScript
**State-Management:** Pinia
**Styling:** Nextcloud Design-System
**Build:** Webpack
**Testing:** PHPUnit (Backend) + Vitest (Frontend)

---

## High-Level-Architektur

```
┌────────────────────────────────────────┐
│  Nextcloud Instanz                     │
│                                        │
│  ┌──────────────────────────────────┐ │
│  │  Souvera User Management App     │ │
│  │                                  │ │
│  │  Frontend (Vue)  ←→  Backend     │ │
│  │                      (PHP)       │ │
│  └──────────────────────────────────┘ │
│                ↓                       │
└────────────────────────────────────────┘
                 ↓
      ┌──────────────────┐
      │  Externe APIs    │
      │  - Domain Check  │
      │  - Lizenz API    │
      └──────────────────┘
```

---

## Nächste Schritte

1. Environment-Setup prüfen
2. App-Grundgerüst erstellen
3. Config-Variablen definieren
4. Iterativ entwickeln (MVP → Features → Polish)
