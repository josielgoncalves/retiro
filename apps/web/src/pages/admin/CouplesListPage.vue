<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md q-gutter-sm">
      <div class="text-h5 font-heading text-primary">Casais</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Novo casal" unelevated to="/app/couples/new" />
    </div>

    <div class="row q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md-4">
        <q-input v-model="search" outlined dense clearable label="Buscar por nome, telefone ou e-mail" @update:model-value="debouncedLoad" />
      </div>
      <div class="col-6 col-md-4">
        <q-select
          v-model="retreatFilter"
          outlined
          dense
          clearable
          emit-value
          map-options
          :options="retreatOptions"
          label="Filtrar por retiro"
          @update:model-value="load"
        />
      </div>
      <div class="col-6 col-md-4">
        <q-select
          v-model="modeFilter"
          outlined
          dense
          clearable
          emit-value
          map-options
          :options="modeOptions"
          label="Modalidade"
          @update:model-value="load"
        />
      </div>
    </div>

    <q-table
      :rows="rows"
      :columns="columns"
      row-key="id"
      flat
      bordered
      class="card--soft"
      :loading="loading"
      v-model:pagination="pagination"
      @request="onRequest"
      @row-click="(_, row) => router.push(`/app/couples/${row.id}`)"
    >
      <template #body-cell-actions="props">
        <q-td :props="props">
          <q-btn
            flat
            dense
            round
            icon="person_add"
            color="primary"
            @click.stop="router.push({ path: '/app/enrollments/new', query: { couple_id: props.row.id } })"
          >
            <q-tooltip>Inscrever em um retiro</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '@/boot/axios';

interface Couple {
  id: number;
  partner_one_name: string;
  partner_two_name: string;
  phone: string;
  email: string | null;
}

const router = useRouter();
const rows = ref<Couple[]>([]);
const loading = ref(false);
const search = ref('');
const retreatFilter = ref<number | null>(null);
const modeFilter = ref<string | null>(null);
const retreatOptions = ref<Array<{ label: string; value: number }>>([]);

const modeOptions = [
  { label: 'Fixa', value: 'fixed' },
  { label: 'Flexível', value: 'flexible' },
];

const columns = [
  { name: 'partner_one_name', label: 'Parceiro 1', field: 'partner_one_name', align: 'left' as const, sortable: true },
  { name: 'partner_two_name', label: 'Parceiro 2', field: 'partner_two_name', align: 'left' as const, sortable: true },
  { name: 'phone', label: 'Telefone', field: 'phone', align: 'left' as const },
  { name: 'email', label: 'E-mail', field: 'email', align: 'left' as const },
  { name: 'actions', label: '', field: 'id', align: 'right' as const },
];

const pagination = ref({
  page: 1,
  rowsPerPage: 15,
  rowsNumber: 0,
});

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
function debouncedLoad() {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => load(), 350);
}

async function load() {
  loading.value = true;
  try {
    const response = await api.get('/couples', {
      params: {
        search: search.value || undefined,
        retreat_id: retreatFilter.value || undefined,
        payment_mode: modeFilter.value || undefined,
        page: pagination.value.page,
        per_page: pagination.value.rowsPerPage,
      },
    });
    rows.value = response.data.data;
    pagination.value.rowsNumber = response.data.meta?.total ?? rows.value.length;
  } finally {
    loading.value = false;
  }
}

function onRequest(requestProp: { pagination: { page: number; rowsPerPage: number } }) {
  pagination.value.page = requestProp.pagination.page;
  pagination.value.rowsPerPage = requestProp.pagination.rowsPerPage;
  void load();
}

onMounted(async () => {
  const response = await api.get('/retreats', { params: { per_page: 100 } });
  retreatOptions.value = response.data.data.map((r: { id: number; name: string }) => ({ label: r.name, value: r.id }));
  await load();
});
</script>
