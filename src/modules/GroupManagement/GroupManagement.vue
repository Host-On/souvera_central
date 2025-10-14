<template>
  <div class="group-management-container">
    <div class="page-header">
      <h2>{{ t('souvera_central', 'Gruppenverwaltung') }}</h2>
      <button class="primary">
        <span class="icon-add"></span>
        {{ t('souvera_central', 'Neue Gruppe') }}
      </button>
    </div>

    <div class="coming-soon">
      <div class="icon-group icon-large"></div>
      <h3>{{ t('souvera_central', 'In Entwicklung') }}</h3>
      <p>{{ t('souvera_central', 'Die Gruppenverwaltung wird in Kürze verfügbar sein.') }}</p>

      <div class="features-list">
        <h4>{{ t('souvera_central', 'Geplante Features') }}:</h4>
        <ul>
          <li><span class="icon-checkmark"></span> {{ t('souvera_central', 'Gruppen erstellen und verwalten') }}</li>
          <li><span class="icon-checkmark"></span> {{ t('souvera_central', 'Benutzer zu Gruppen zuweisen') }}</li>
          <li><span class="icon-checkmark"></span> {{ t('souvera_central', 'Gruppenberechtigungen konfigurieren') }}</li>
          <li><span class="icon-checkmark"></span> {{ t('souvera_central', 'Gruppenübersicht und Statistiken') }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
  name: 'GroupManagement',

  methods: {
    t
  }
}
</script>

<style scoped>
.group-management-container {
  padding: 30px;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--color-border);
}

.page-header h2 {
  margin: 0;
  font-size: 28px;
  font-weight: 600;
}

.coming-soon {
  text-align: center;
  padding: 80px 20px;
  background: var(--color-background-dark);
  border-radius: var(--border-radius-large);
  margin-top: 40px;
}

.icon-large {
  font-size: 80px;
  opacity: 0.3;
  margin-bottom: 20px;
  color: var(--color-primary-element);
}

.coming-soon h3 {
  font-size: 24px;
  font-weight: 600;
  margin-bottom: 10px;
  color: var(--color-main-text);
}

.coming-soon p {
  margin: 0 0 30px;
  color: var(--color-text-lighter);
  font-size: 16px;
}

.features-list {
  max-width: 500px;
  margin: 0 auto;
  text-align: left;
  padding: 20px;
  background: var(--color-main-background);
  border-radius: var(--border-radius-large);
}

.features-list h4 {
  margin: 0 0 15px;
  font-size: 16px;
  font-weight: 600;
  color: var(--color-main-text);
}

.features-list ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.features-list li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  color: var(--color-text-lighter);
}

.features-list li .icon-checkmark {
  color: var(--color-success);
  opacity: 0.7;
}
</style>
