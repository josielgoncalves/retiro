<template>
  <q-page class="q-pa-md">
    <div class="text-h5 font-heading text-primary q-mb-md">Nova inscrição</div>

    <q-card class="card--soft q-pa-md" style="max-width: 640px;">
      <q-form class="q-gutter-md" @submit.prevent="handleSubmit">
        <q-select
          v-model="form.retreat_id"
          outlined
          label="Retiro *"
          :options="retreatOptions"
          emit-value
          map-options
          :rules="[required]"
        />
        <q-select
          v-model="form.couple_id"
          outlined
          label="Casal *"
          :options="coupleOptions"
          emit-value
          map-options
          use-input
          input-debounce="300"
          @filter="filterCouples"
          :rules="[required]"
        />

        <q-input
          v-model.number="form.total_amount_reais"
          outlined
          type="number"
          step="0.01"
          min="0.01"
          label="Valor contratado (R$) *"
          :rules="[required]"
        />

        <q-option-group
          v-model="form.payment_mode"
          :options="[
            { label: 'Fixa (com parcelas)', value: 'fixed' },
            { label: 'Flexível (sem parcelas)', value: 'flexible' },
          ]"
          color="primary"
          inline
        />

        <div v-if="form.payment_mode === 'fixed'" class="q-gutter-md">
          <div class="text-subtitle2">Geração das parcelas</div>
          <div class="row q-col-gutter-sm">
            <q-input class="col-12 col-sm-4" v-model.number="form.installment_count" outlined type="number" min="1" max="120" label="Quantidade *" />
            <q-input class="col-12 col-sm-4" v-model="form.first_due_date" outlined type="date" label="Primeiro vencimento *" />
            <q-select class="col-12 col-sm-4" v-model="form.periodicity" outlined label="Periodicidade *" :options="periodicityOptions" emit-value map-options />
          </div>
          <q-btn color="secondary" outline icon="auto_awesome" label="Gerar parcelas" :loading="generating" @click="generateInstallments" />

          <div v-if="form.installments.length" class="text-subtitle2">Prévia editável</div>

          <div v-for="(installment, index) in form.installments" :key="index" class="row items-center q-col-gutter-sm q-mb-sm">
            <div class="col-1 text-caption">{{ index + 1 }}ª</div>
            <div class="col-5">
              <q-input v-model="installment.due_date" outlined dense type="date" :label="`Vencimento parcela ${index + 1}`" />
            </div>
            <div class="col-6">
              <q-input v-model.number="installment.amount_reais" outlined dense type="number" min="0.01" step="0.01" label="Valor (R$)" />
            </div>
          </div>

          <div v-if="form.installments.length" class="text-caption" :class="installmentDifference === 0 ? 'text-positive' : 'text-negative'">
            Soma: {{ formatCents(installmentSum) }}<span v-if="installmentDifference !== 0"> · diferença: {{ formatCents(Math.abs(installmentDifference)) }}</span>
          </div>
        </div>

        <div class="row justify-end q-gutter-sm">
          <q-btn flat label="Cancelar" to="/app/couples" />
          <q-btn type="submit" color="primary" unelevated label="Inscrever" :loading="saving" />
        </div>
      </q-form>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { api } from '@/boot/axios';
import { formatCents } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const $q = useQuasar();

const saving = ref(false);
const generating = ref(false);
const retreatOptions = ref<Array<{ label: string; value: number }>>([]);
const allCoupleOptions = ref<Array<{ label: string; value: number }>>([]);
const coupleOptions = ref<Array<{ label: string; value: number }>>([]);

const form = reactive({
  retreat_id: null as number | null,
  couple_id: (route.query.couple_id ? Number(route.query.couple_id) : null) as number | null,
  payment_mode: 'flexible',
  total_amount_reais: 0,
  installment_count: 1,
  first_due_date: '',
  periodicity: 'monthly',
  installments: [] as Array<{ due_date: string; amount_reais: number }>,
});

const periodicityOptions = [
  { label: 'Mensal', value: 'monthly' },
  { label: 'Semanal', value: 'weekly' },
];

function required(val: unknown) {
  return (val !== null && val !== '' && val !== undefined) || 'Campo obrigatório';
}

const installmentSum = computed(() => form.installments.reduce((sum, item) => sum + Math.round(item.amount_reais * 100), 0));
const installmentDifference = computed(() => Math.round(form.total_amount_reais * 100) - installmentSum.value);

async function generateInstallments() {
  generating.value = true;
  try {
    const response = await api.post('/installments/preview', {
      total_amount_cents: Math.round(form.total_amount_reais * 100),
      installment_count: form.installment_count,
      first_due_date: form.first_due_date,
      periodicity: form.periodicity,
    });
    form.installments = response.data.data.map((item: { due_date: string; amount_cents: number }) => ({
      due_date: item.due_date,
      amount_reais: item.amount_cents / 100,
    }));
  } catch (err) {
    const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Não foi possível gerar as parcelas.';
    $q.notify({ type: 'negative', message });
  } finally {
    generating.value = false;
  }
}

function filterCouples(val: string, update: (callback: () => void) => void) {
  update(() => {
    const needle = val.toLowerCase();
    coupleOptions.value = allCoupleOptions.value.filter((c) => c.label.toLowerCase().includes(needle));
  });
}

onMounted(async () => {
  const [retreatsResponse, couplesResponse] = await Promise.all([
    api.get('/retreats', { params: { per_page: 100 } }),
    api.get('/couples', { params: { per_page: 200 } }),
  ]);

  retreatOptions.value = retreatsResponse.data.data.map((r: { id: number; name: string }) => ({ label: r.name, value: r.id }));
  allCoupleOptions.value = couplesResponse.data.data.map((c: { id: number; partner_one_name: string; partner_two_name: string }) => ({
    label: `${c.partner_one_name} & ${c.partner_two_name}`,
    value: c.id,
  }));
  coupleOptions.value = allCoupleOptions.value;
});

async function handleSubmit() {
  if (form.payment_mode === 'fixed' && (form.installments.length === 0 || installmentDifference.value !== 0)) {
    $q.notify({ type: 'negative', message: 'Gere as parcelas e confira se a soma corresponde ao valor contratado.' });
    return;
  }
  saving.value = true;
  try {
    const payload: Record<string, unknown> = {
      retreat_id: form.retreat_id,
      couple_id: form.couple_id,
      payment_mode: form.payment_mode,
      total_amount_cents: Math.round(form.total_amount_reais * 100),
    };

    if (form.payment_mode === 'fixed') {
      payload.installments = form.installments.map((i) => ({ due_date: i.due_date, amount_cents: Math.round(i.amount_reais * 100) }));
    }

    const response = await api.post('/enrollments', payload);
    $q.notify({ type: 'positive', message: 'Inscrição criada com sucesso.' });
    await router.push(`/app/enrollments/${response.data.data.id}`);
  } catch (err) {
    const message =
      (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
      'Não foi possível criar a inscrição.';
    $q.notify({ type: 'negative', message });
  } finally {
    saving.value = false;
  }
}
</script>
