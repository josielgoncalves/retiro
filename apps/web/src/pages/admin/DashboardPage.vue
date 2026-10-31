<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md q-gutter-sm">
      <div class="text-h5 font-heading text-primary">Dashboard</div>
      <q-space />
      <q-select
        v-model="selectedRetreatId"
        :options="retreatOptions"
        outlined
        dense
        emit-value
        map-options
        label="Retiro"
        style="min-width: 240px;"
      />
    </div>

    <div v-if="!selectedRetreatId" class="text-muted">
      Selecione um retiro para visualizar os indicadores.
    </div>

    <template v-else-if="data">
      <div class="row q-col-gutter-md">
        <div class="col-6 col-md-3">
          <StatCard label="Casais ativos" :value="String(data.active_enrollments_count)" icon="favorite" />
        </div>
        <div class="col-6 col-md-3">
          <StatCard label="Valor contratado" :value="formatCents(data.total_contracted_cents)" icon="request_quote" />
        </div>
        <div class="col-6 col-md-3">
          <StatCard label="Valor recebido" :value="formatCents(data.total_received_cents)" icon="payments" color="positive" />
        </div>
        <div class="col-6 col-md-3">
          <StatCard label="Saldo a receber" :value="formatCents(data.balance_cents)" icon="hourglass_bottom" color="warning" />
        </div>
        <div class="col-6 col-md-3">
          <StatCard label="Inscrições quitadas" :value="String(data.settled_enrollments_count)" icon="check_circle" color="positive" />
        </div>
        <div class="col-6 col-md-3">
          <StatCard label="Inscrições com saldo" :value="String(data.enrollments_with_balance_count)" icon="pending" />
        </div>
        <div class="col-6 col-md-3">
          <StatCard label="Vencido (parcelas fixas)" :value="formatCents(data.total_overdue_cents)" icon="warning" color="negative" />
        </div>
      </div>

      <div class="row q-col-gutter-md q-mt-md">
        <div class="col-12 col-md-6">
          <q-card class="card--soft">
            <q-card-section>
              <div class="text-subtitle1 text-weight-medium">Recebimentos por mês</div>
            </q-card-section>
            <q-list separator>
              <q-item v-for="(amount, month) in data.receipts_by_month" :key="month">
                <q-item-section>{{ formatMonth(month) }}</q-item-section>
                <q-item-section side>{{ formatCents(amount) }}</q-item-section>
              </q-item>
              <q-item v-if="Object.keys(data.receipts_by_month).length === 0">
                <q-item-section class="text-muted">Nenhum recebimento no período.</q-item-section>
              </q-item>
            </q-list>
          </q-card>
        </div>

        <div class="col-12 col-md-6">
          <q-card class="card--soft">
            <q-card-section>
              <div class="text-subtitle1 text-weight-medium">Últimos pagamentos</div>
            </q-card-section>
            <q-list separator>
              <q-item v-for="payment in data.last_payments" :key="payment.id">
                <q-item-section>
                  <q-item-label class="notranslate" translate="no">{{ formatCents(payment.amount_cents) }}</q-item-label>
                  <q-item-label caption><span class="notranslate" translate="no">{{ formatDateTimeBR(payment.paid_at) }}</span> · {{ methodLabel(payment.payment_method) }}</q-item-label>
                </q-item-section>
              </q-item>
              <q-item v-if="data.last_payments.length === 0">
                <q-item-section class="text-muted">Nenhum pagamento registrado ainda.</q-item-section>
              </q-item>
            </q-list>
          </q-card>
        </div>
      </div>
    </template>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { api } from '@/boot/axios';
import { formatCents, formatDateTimeBR, methodLabel } from '@/utils/format';
import StatCard from '@/components/StatCard.vue';

interface Retreat {
  id: number;
  name: string;
}

interface DashboardData {
  active_enrollments_count: number;
  total_contracted_cents: number;
  total_received_cents: number;
  balance_cents: number;
  settled_enrollments_count: number;
  enrollments_with_balance_count: number;
  total_overdue_cents: number;
  receipts_by_month: Record<string, number>;
  last_payments: Array<{ id: number; amount_cents: number; paid_at: string; payment_method: string }>;
}

const retreats = ref<Retreat[]>([]);
const retreatOptions = ref<Array<{ label: string; value: number }>>([]);
const selectedRetreatId = ref<number | null>(null);
const data = ref<DashboardData | null>(null);

function formatMonth(value: string): string {
  const [year, month] = value.split('-');
  const date = new Date(Number(year), Number(month) - 1, 1);
  return date.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
}

async function loadRetreats() {
  const response = await api.get('/retreats', { params: { per_page: 100 } });
  retreats.value = response.data.data;
  retreatOptions.value = retreats.value.map((r) => ({ label: r.name, value: r.id }));
  if (retreats.value.length > 0 && !selectedRetreatId.value) {
    selectedRetreatId.value = retreats.value[0]?.id ?? null;
  }
}

async function loadDashboard() {
  if (!selectedRetreatId.value) return;
  const response = await api.get('/dashboard', { params: { retreat_id: selectedRetreatId.value } });
  data.value = response.data.data;
}

watch(selectedRetreatId, loadDashboard);

onMounted(async () => {
  await loadRetreats();
  await loadDashboard();
});
</script>
