<template>
  <q-layout view="hHh lpR fFf">
    <q-header class="bg-primary text-white" elevated>
      <q-toolbar class="q-py-sm">
        <q-toolbar-title class="font-heading text-center">
          Retiro de Casais
        </q-toolbar-title>
      </q-toolbar>
    </q-header>

    <q-page-container style="background: #FAF6EC;">
      <router-view />
    </q-page-container>

    <q-footer bordered class="bg-white">
      <system-footer />
    </q-footer>
  </q-layout>
</template>

<script setup lang="ts">
import SystemFooter from '@/components/SystemFooter.vue';
</script>
