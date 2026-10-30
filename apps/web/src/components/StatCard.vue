<template>
  <q-card class="card--soft">
    <q-card-section class="row items-center no-wrap">
      <q-icon :name="icon" :color="color" size="32px" class="q-mr-md" />
      <div>
        <div class="text-caption text-muted">{{ label }}</div>
        <div class="text-h6 text-weight-bold">{{ value }}</div>
      </div>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string;
    value: string;
    icon: string;
    color?: string;
  }>(),
  { color: 'primary' },
);
</script>
