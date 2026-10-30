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

        <div v-if="form.payment_mode === 'fixed'">
          <div class="row items-center q-mb-sm">
            <div class="text-subtitle2">Parcelas</div>
            <q-space />
            <q-btn flat dense icon="add" label="Adicionar parcela" @click="addInstallment" />
          </div>

          <div v-for="(installment, index) in form.installments" :key="index" class="row items-center q-col-gutter-sm q-mb-sm">
            <div class="col">
              <q-input v-model="installment.due_date" outlined dense type="date" :label="`Vencimento parcela ${index + 1}`" />
            </div>
            <div class="col-auto">
              <q-btn flat dense round icon="close" @click="removeInstallment(index)" />
            </div>
          </div>

          <div v-if="estimatedInstallmentAmount" class="text-caption text-muted">
            Valor estimado por parcela: {{ estimatedInstallmentAmount }}
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
const retreatOptions = ref<Array<{ label: string; value: number }>>([]);
const allCoupleOptions = ref<Array<{ label: string; value: number }>>([]);
const coupleOptions = ref<Array<{ label: string; value: number }>>([]);

const form = reactive({
  retreat_id: null as number | null,
  couple_id: (route.query.couple_id ? Number(route.query.couple_id) : null) as number | null,
  payment_mode: 'flexible',
  total_amount_reais: 0,
  installments: [] as Array<{ due_date: string }>,
});

function required(val: unknown) {
  return (val !== null && val !== '' && val !== undefined) || 'Campo obrigatório';
}

function addInstallment() {
  form.installments.push({ due_date: '' });
}
function removeInstallment(index: number) {
  form.installments.splice(index, 1);
}

const estimatedInstallmentAmount = computed(() => {
  if (form.installments.length === 0 || !form.total_amount_reais) return null;
  const cents = Math.round(form.total_amount_reais * 100);
  return formatCents(Math.floor(cents / form.installments.length));
});

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
  saving.value = true;
  try {
    const payload: Record<string, unknown> = {
      retreat_id: form.retreat_id,
      couple_id: form.couple_id,
      payment_mode: form.payment_mode,
      total_amount_cents: Math.round(form.total_amount_reais * 100),
    };

    if (form.payment_mode === 'fixed') {
      payload.installments = form.installments.map((i) => ({ due_date: i.due_date }));
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
