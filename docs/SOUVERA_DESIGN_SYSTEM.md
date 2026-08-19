# Souvera Design System & Layout-Leitfaden

> **Zweck:** Damit **alle** Souvera-Apps (Central, Shield, Mail, …) optisch wie
> **aus einem Guss** wirken. Dieser Leitfaden beschreibt exakt das Layout und die
> Konventionen von **Souvera Central**. Andere App-Agents bauen ihre Oberfläche
> **1:1 nach diesem Muster**.
>
> **Grundprinzip:** Wir nutzen ausschließlich die **nativen Nextcloud-Komponenten
> und Theme-Variablen** (`@nextcloud/vue` v9, NC 34). Kein eigenes Farbschema,
> keine eigenen Layout-Rahmen. Dadurch passt sich alles automatisch an Light-/
> Dark-Theme und das Hoster-Theming an.

---

## 1. Tech-Stack (verbindlich)

| Baustein | Vorgabe |
|---|---|
| Frontend | **Vue 3** (Options API, wie in Central) |
| UI-Kit | **`@nextcloud/vue` v9** – Import je Komponente: `@nextcloud/vue/components/NcXxx` |
| Icons | **`vue-material-design-icons`** (kein Emoji, keine eigenen SVGs im UI) |
| Build | **Webpack** (`yarn build`), Ausgabe nach `js/<appid>-<entry>.js` |
| i18n | `@nextcloud/l10n` (`translate as t`, `translatePlural as n`) |
| HTTP | `@nextcloud/axios` + `@nextcloud/router` (`generateUrl`) |
| Toasts | `@nextcloud/dialogs` (`showSuccess` / `showError`) |

---

## 2. App-Shell (das Herzstück des Layouts)

**Jede** Souvera-Seite verwendet exakt diese Schachtelung:
`NcContent` → (`NcAppNavigation` links) + (`NcAppContent` rechts).

```vue
<template>
    <NcContent app-name="souvera_shield">           <!-- eigene App-ID -->
        <NcAppNavigation data-testid="shield-navigation"
                         :aria-label="t('souvera_shield', 'Navigation')">
            <template #list>
                <NcAppNavigationItem
                    v-for="item in navigationItems"
                    :key="item.id"
                    :name="item.label"
                    :active="currentRoute === item.id"
                    :data-testid="`nav-${item.id}`"
                    @click="handleNavigation(item.id, item.url)">
                    <template #icon>
                        <component :is="item.icon" :size="20" />
                    </template>
                </NcAppNavigationItem>
            </template>
        </NcAppNavigation>

        <NcAppContent>
            <div class="souvera-content" data-testid="shield-content">
                <!-- Seiteninhalt / Module -->
            </div>
        </NcAppContent>
    </NcContent>
</template>
```

**Regeln:**
- `NcContent[app-name]` = die eigene App-ID.
- Navigation **immer** über `#list` + `NcAppNavigationItem` (Icon im `#icon`-Slot, `:size="20"`).
- Aktiver Eintrag über `:active`. Icons via `markRaw(...)` in den Daten halten.
- Inhalt in einen `.souvera-content`-Wrapper:
  ```css
  .souvera-content { width: 100%; min-height: 100%; box-sizing: border-box; }
  ```
- Navigationslinks nie unterstreichen:
  ```css
  :deep(.app-navigation-entry-link),
  :deep(.app-navigation-entry__title),
  :deep(.app-navigation a) { text-decoration: none; }
  ```

### Navigations-Datenmodell (aus Central)

```js
import { markRaw } from 'vue'
import { generateUrl } from '@nextcloud/router'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'

navigationItems: [
    {
        id: 'dashboard',
        label: t('souvera_shield', 'Dashboard'),
        icon: markRaw(ViewDashboard),
        url: generateUrl('/apps/souvera_shield/dashboard'),
    },
    // ...
]
```

---

## 3. Mount-Punkt, Template & Server-Routing

**PHP-Template** (`templates/main.php`) – lädt das Bundle und definiert den Mount:

```php
<?php
script('souvera_shield', 'souvera_shield-main');
style('souvera_shield', 'main');
?>
<div id="souvera-shield-app" data-initial-route="<?php p($_['initialRoute'] ?? 'dashboard'); ?>">
    <div class="loading-container">
        <div class="icon-loading"></div>
        <p><?php p($l->t('Lade …')); ?></p>
    </div>
</div>
```

**Mount-CSS** (`css/main.css`) – füllt den NC-Content-Bereich, damit `NcContent`
das native Layout korrekt aufspannt:

```css
#souvera-shield-app {
    width: 100%; height: 100%;
    display: flex; flex: 1 1 auto; min-height: 0;
}
.loading-container {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; width: 100%; height: 100%; gap: 15px;
}
.loading-container p { color: var(--color-text-maxcontrast); }
```

**Entry** (`src/main.js`):

```js
import { createApp } from 'vue'
import App from './App.vue'
import './styles/forms.css'
createApp(App).mount('#souvera-shield-app')
```

---

## 4. Webpack-Build

```js
// webpack.config.js (Auszug)
entry: {
    main: path.join(__dirname, 'src', 'main.js'),
},
output: {
    path: path.resolve(__dirname, 'js'),
    filename: 'souvera_shield-[name].js',
    clean: false,
},
resolve: { extensions: ['.js', '.mjs', '.vue'] },
// @nextcloud/vue liefert .mjs "fully specified":
module: { rules: [{ test: /\.m?js$/, resolve: { fullySpecified: false } }, /* … */] }
```

Nach jeder Änderung an `.vue`/`.js`: **`yarn build`**.

---

## 5. Design-Tokens (`src/styles/forms.css`)

**1:1 aus Central übernehmen.** Diese Tokens leiten sich aus NC-Theme-Variablen
ab → automatisches Light/Dark. Sie garantieren **gleiche Höhen, Radien, Rahmen,
Fokus** über alle Apps.

```css
:root {
    --sc-control-height: 44px;
    --sc-control-padding-x: 16px;
    --sc-control-padding-y: 10px;
    --sc-control-radius: var(--border-radius-large, 8px);
    --sc-control-border-width: 1.5px;
    --sc-field-gap: 24px;      /* Abstand zwischen Formularfeldern */
    --sc-section-gap: 40px;    /* Abstand zwischen Abschnitten */
    --sc-focus-ring: 0 0 0 3px rgba(var(--color-primary-element-rgb, 0, 130, 201), 0.25);
}
```

- **Steuerhöhe:** immer `44px` (`--default-clickable-area`).
- **Radius:** `--border-radius-large` (12px) für Karten/Inputs/Buttons.
- **Feld-/Abschnittsabstände:** `--sc-field-gap` / `--sc-section-gap`.

---

## 6. Farben – NUR Nextcloud-Variablen (nie Hex im Code)

| Zweck | Variable |
|---|---|
| Haupt-Hintergrund | `--color-main-background` |
| Hover/gedämpft | `--color-background-hover`, `--color-background-dark` |
| Haupttext | `--color-main-text` |
| Gedämpfter Text | `--color-text-maxcontrast` |
| Primär (Souvera-Blau kommt vom Theme) | `--color-primary-element`, `--color-primary-element-rgb` |
| Rahmen | `--color-border`, `--color-border-maxcontrast` |
| Status | `--color-success`, `--color-warning`, `--color-error` (+ `*-rgb`) |
| Radien | `--border-radius`, `--border-radius-large`, `--border-radius-pill` |

> **Verboten:** feste Hex-/RGB-Farben im UI. Immer Variablen → Theme-kompatibel.

---

## 7. Typografie & Abstände

- H1: `text-4xl … text-6xl`-Äquivalent → Seitentitel ~`1.7rem`, `font-weight: var(--font-weight-heading, 700)`.
- H2/Sektion: ~`1.3rem`. Body: `1rem` (mobil `0.875rem`). Klein/Meta: `0.8rem`, `--color-text-maxcontrast`.
- Zeilenhöhe Fließtext: ~`1.6`.
- Content links-/asymmetrisch ausrichten; Artikeltexte auf `max-width: 820px` begrenzen (bessere Lesbarkeit).

---

## 8. Icons

- Aus `vue-material-design-icons/<Name>.vue`, in Navigation als `markRaw(...)`.
- Standardgröße in der Nav: `:size="20"`; in Empty-States größer.
- **Keine Emojis** als Icons.
- Beispiele aus Central: `ViewDashboard`, `AccountMultiple`, `AccountGroup`,
  `EmailMultiple`, `Cog`. Hilfe: `BookOpenPageVariant`, `FolderOutline`,
  `FileDocumentOutline`, `HelpCircleOutline`.

---

## 9. Bedienelemente (native NC-Komponenten)

| Element | Komponente |
|---|---|
| Button | `NcButton` (`type="primary" \| "secondary" \| "tertiary" \| "error"`) |
| Textfeld | `NcTextField` |
| Auswahl | `NcSelect` |
| Checkbox/Switch | `NcCheckboxRadioSwitch` |
| Dialog/Modal | `NcDialog` / `NcModal` |
| Leerzustand | `NcEmptyContent` (Slots `#icon`, `#description`, `#action`) |
| Ladeanzeige | `NcLoadingIcon` (`:size="44"` zentriert) |
| Überschrift in Nav | `NcAppNavigationCaption` |

**Leerzustand-Muster:**

```vue
<NcEmptyContent :name="t('souvera_shield', 'Nichts gefunden')">
    <template #icon><ShieldOutline /></template>
    <template #description>{{ t('souvera_shield', 'Es gibt hier noch keine Einträge.') }}</template>
</NcEmptyContent>
```

**Feedback (Toasts):**

```js
import { showSuccess, showError } from '@nextcloud/dialogs'
showSuccess(t('souvera_shield', 'Gespeichert.'))
showError(t('souvera_shield', 'Fehler beim Speichern.'))
```

---

## 10. Backend-Antworten robust auspacken

Central-Controller können OCS- oder Plain-Responses liefern. Immer so lesen:

```js
const data = response.data.ocs?.data || response.data.data || response.data
```

---

## 11. Navigation registrieren (dynamisch, in `Application::boot()`)

**Kein** statischer `<navigation>`-Block in `info.xml` (crasht unter NC34 bei
Gästen). Stattdessen dynamisch – und an die **tatsächliche Berechtigung**
gekoppelt, damit keine toten Menüeinträge entstehen:

```php
public function boot(IBootContext $context): void {
    $context->injectFn(function (
        IUserSession $userSession,
        INavigationManager $navigationManager,
        IURLGenerator $urlGenerator,
        IL10N $l10n,
    ): void {
        $user = $userSession->getUser();
        if ($user === null) { return; }

        // App-Menü (oben): type 'link' (Default)
        $navigationManager->add(static fn (): array => [
            'id' => 'souvera_shield',
            'order' => 20,
            'href' => $urlGenerator->linkToRoute('souvera_shield.page.index'),
            'icon' => $urlGenerator->imagePath('souvera_shield', 'app.svg'),
            'name' => $l10n->t('Shield'),
        ]);

        // Nutzer-Menü oben rechts (Avatar-Dropdown): type 'settings'
        $navigationManager->add(static fn (): array => [
            'id' => 'souvera_shield_help',
            'order' => 5,
            'type' => 'settings',
            'href' => $urlGenerator->linkToRoute('souvera_shield.help.index'),
            'icon' => $urlGenerator->imagePath('souvera_shield', 'app.svg'),
            'name' => $l10n->t('Hilfe'),
        ]);
    });
}
```

- **App-Icon (oben):** `type` weglassen (Default `'link'`).
- **Nutzer-Menü (oben rechts, „Auf Namen klicken"):** `type => 'settings'`.
- Sichtbarkeit an Berechtigung/Gruppen koppeln (siehe Central `PermissionService`).

---

## 12. `data-testid`-Konvention (verbindlich)

Jedes interaktive Element und jede kritische Anzeige bekommt ein eindeutiges,
kebab-case `data-testid`, das die **Funktion** beschreibt:

```
shield-navigation, nav-dashboard, shield-content,
rules-table, add-rule-button, save-settings-button, error-banner
```

---

## 13. Mehrsprachigkeit (l10n)

- Quelltexte **auf Deutsch** in `t('souvera_shield', 'Deutscher Text')` /
  `n('souvera_shield', 'Singular', 'Plural', count)`.
- Übersetzungen unter `l10n/<lang>.js` + `l10n/<lang>.json` (NC-Format
  `OC.L10N.register(...)` bzw. `{translations, pluralForm}`). Central liefert
  DE (Quelle) + EN + NL.

---

## 14. Checkliste „Sieht aus wie Central"

- [ ] `NcContent` + `NcAppNavigation` (`#list` → `NcAppNavigationItem`) + `NcAppContent`
- [ ] Nur NC-Theme-Variablen für Farben/Radien; **keine** festen Hex-Werte
- [ ] `forms.css`-Tokens übernommen (44px Höhe, `--border-radius-large`, Feld-/Abschnittsabstände)
- [ ] Icons aus `vue-material-design-icons`, `:size="20"` in der Nav, `markRaw`
- [ ] `NcButton`/`NcTextField`/`NcSelect`/`NcEmptyContent`/`NcLoadingIcon` statt Eigenbau
- [ ] Toasts via `@nextcloud/dialogs`
- [ ] Response-Unwrap `ocs?.data || data || response.data`
- [ ] Navigation dynamisch in `boot()`, Sichtbarkeit an Berechtigung gekoppelt
- [ ] Hilfe-Eintrag im Nutzer-Menü über `type => 'settings'`
- [ ] `data-testid` an allen interaktiven/kritischen Elementen
- [ ] Deutsche Quelltexte via `t()`/`n()`, `l10n/`-Dateien gepflegt
- [ ] Gemeinsame Zugangsdaten (z. B. provider.tools) **nicht** lokal speichern,
      sondern zentral aus Central beziehen → siehe `docs/SHARED_PROVIDER_TOKEN.md`
