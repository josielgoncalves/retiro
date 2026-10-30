<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5 font-heading text-primary">Retiros</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Novo retiro" unelevated to="/app/retreats/new" />
    </div>

    <q-table
      :rows="rows"
      :columns="columns"
      row-key="id"
      flat
      bordered
      class="card--soft"
      :loading="loading"
      @row-click="(_, row) => router.push(`/app/retreats/${row.id}`)"
    >
      <template #body-cell-status="props">
        <q-td :props="props">
          <q-badge :color="statusColor(props.value)">{{ statusLabel(props.value) }}</q-badge>
        </q-td>
      </template>
      <template #body-cell-dates="props">
        <q-td :props="props">
          {{ formatDate(props.row.start_date) }} – {{ formatDate(props.row.end_date) }}
        </q-td>
      </template>
    </q-table>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '@/boot/axios';
import { formatDate } from '@/utils/format';

interface Retreat {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  location: string | null;
  status: string;
  enrollments_count: number;
}

const router = useRouter();
const rows = ref<Retreat[]>([]);
const loading = ref(false);

const columns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left' as const, sortable: true },
  { name: 'dates', label: 'Período', field: 'start_date', align: 'left' as const },
  { name: 'location', label: 'Local', field: 'location', align: 'left' as const },
  { name: 'enrollments_count', label: 'Inscrições', field: 'enrollments_count', align: 'center' as const },
  { name: 'status', label: 'Situação', field: 'status', align: 'center' as const },
];

const statusLabels: Record<string, string> = {
  draft: 'Rascunho',
  active: 'Ativo',
  finished: 'Finalizado',
  archived: 'Arquivado',
};
const statusColors: Record<string, string> = {
  draft: 'grey-6',
  active: 'positive',
  finished: 'primary',
  archived: 'grey-8',
};

function statusLabel(status: string) {
  return statusLabels[status] ?? status;
}
function statusColor(status: string) {
  return statusColors[status] ?? 'grey-6';
}

onMounted(async () => {
  loading.value = true;
  try {
    const response = await api.get('/retreats', { params: { per_page: 50 } });
    rows.value = response.data.data;
  } finally {
    loading.value = false;
  }
});
</script>
