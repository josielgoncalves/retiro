<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md q-gutter-sm">
      <div class="text-h5 font-heading text-primary">Relatórios</div>
      <q-space />
      <q-select
        v-model="retreatId"
        outlined
        dense
        emit-value
        map-options
        :options="retreatOptions"
        label="Retiro"
        style="min-width: 220px;"
        @update:model-value="load"
      />
      <q-btn color="primary" outline icon="download" label="Exportar CSV" @click="exportCsv" :disable="!retreatId" />
    </div>

    <q-card class="card--soft q-mb-md" v-if="report">
      <q-table
        :rows="report.enrollments"
        :columns="columns"
        row-key="enrollment_id"
        flat
        :pagination="{ rowsPerPage: 20 }"
      >
        <template #body-cell-status="props">
          <q-td :props="props">
            <q-badge :color="props.row.balance_cents === 0 ? 'positive' : 'warning'">
              {{ props.row.balance_cents === 0 ? 'Quitado' : 'Com saldo' }}
            </q-badge>
          </q-td>
        </template>
      </q-table>
    </q-card>

    <q-card class="card--soft" v-if="report && report.overdue_installments.length > 0">
      <q-card-section>
        <div class="text-subtitle1 text-weight-medium text-negative">Parcelas vencidas</div>
      </q-card-section>
      <q-list separator>
        <q-item v-for="(item, idx) in report.overdue_installments" :key="idx">
          <q-item-section>
            <q-item-label>{{ item.couple_name }} — parcela {{ item.installment_number }}</q-item-label>
            <q-item-label caption>Venceu em {{ formatDate(item.due_date) }}</q-item-label>
          </q-item-section>
          <q-item-section side>{{ formatCents(item.balance_cents) }}</q-item-section>
        </q-item>
      </q-list>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import { formatCents, formatDate } from '@/utils/format';

interface EnrollmentRow {
  enrollment_id: number;
  couple_name: string;
  payment_mode: string;
  total_amount_cents: number;
  total_paid_cents: number;
  balance_cents: number;
}

interface Report {
  enrollments: EnrollmentRow[];
  overdue_installments: Array<{ couple_name: string; installment_number: number; due_date: string; balance_cents: number }>;
}

const retreatOptions = ref<Array<{ label: string; value: number }>>([]);
const retreatId = ref<number | null>(null);
const report = ref<Report | null>(null);

const columns = [
  { name: 'couple_name', label: 'Casal', field: 'couple_name', align: 'left' as const },
  { name: 'payment_mode', label: 'Modalidade', field: (row: EnrollmentRow) => (row.payment_mode === 'fixed' ? 'Fixa' : 'Flexível'), align: 'left' as const },
  { name: 'total_amount_cents', label: 'Contratado', field: (row: EnrollmentRow) => formatCents(row.total_amount_cents), align: 'right' as const },
  { name: 'total_paid_cents', label: 'Recebido', field: (row: EnrollmentRow) => formatCents(row.total_paid_cents), align: 'right' as const },
  { name: 'balance_cents', label: 'Saldo', field: (row: EnrollmentRow) => formatCents(row.balance_cents), align: 'right' as const },
  { name: 'status', label: 'Situação', field: 'balance_cents', align: 'center' as const },
];

async function load() {
  if (!retreatId.value) return;
  const response = await api.get('/reports/financial', { params: { retreat_id: retreatId.value } });
  report.value = response.data.data;
}

async function exportCsv() {
  const response = await api.get('/reports/financial/export', {
    params: { retreat_id: retreatId.value },
    responseType: 'blob',
  });
  const url = window.URL.createObjectURL(new Blob([response.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = `relatorio-financeiro-${retreatId.value}.csv`;
  link.click();
  window.URL.revokeObjectURL(url);
}

onMounted(async () => {
  const response = await api.get('/retreats', { params: { per_page: 100 } });
  retreatOptions.value = response.data.data.map((r: { id: number; name: string }) => ({ label: r.name, value: r.id }));
  if (retreatOptions.value.length > 0) {
    retreatId.value = retreatOptions.value[0]?.value ?? null;
    await load();
  }
});
</script>
