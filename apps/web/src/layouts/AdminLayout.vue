<template>
  <q-layout view="lHh Lpr lFf">
    <q-header class="bg-primary text-white" elevated>
      <q-toolbar>
        <q-btn
          flat
          dense
          round
          icon="menu"
          aria-label="Menu"
          class="lt-md"
          @click="drawerOpen = !drawerOpen"
        />
        <q-toolbar-title class="font-heading">
          Portal do Retiro de Casais
        </q-toolbar-title>
        <q-btn flat round icon="logout" aria-label="Sair" @click="handleLogout" />
      </q-toolbar>
    </q-header>

    <q-drawer v-model="drawerOpen" show-if-above bordered class="bg-cream">
      <q-list padding>
        <q-item-label header class="text-weight-bold">
          {{ auth.user?.name }}
        </q-item-label>
        <q-item-label caption class="q-px-md q-mb-sm text-muted">
          {{ auth.user?.role === 'admin' ? 'Administrador' : 'Operador' }}
        </q-item-label>

        <q-item
          v-for="link in links"
          :key="link.to"
          clickable
          :to="link.to"
          active-class="text-primary bg-white"
        >
          <q-item-section avatar>
            <q-icon :name="link.icon" />
          </q-item-section>
          <q-item-section>{{ link.label }}</q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const drawerOpen = ref(false);
const auth = useAuthStore();
const router = useRouter();

const links = computed(() => {
  const base = [
    { label: 'Dashboard', icon: 'space_dashboard', to: '/app/dashboard' },
    { label: 'Retiros', icon: 'event', to: '/app/retreats' },
    { label: 'Casais', icon: 'favorite', to: '/app/couples' },
    { label: 'Avisos', icon: 'campaign', to: '/app/announcements' },
    { label: 'Relatórios', icon: 'bar_chart', to: '/app/reports' },
  ];

  if (auth.isAdmin) {
    base.push({ label: 'Usuários', icon: 'admin_panel_settings', to: '/app/users' });
  }

  return base;
});

async function handleLogout() {
  await auth.logout();
  await router.push('/login');
}
</script>
