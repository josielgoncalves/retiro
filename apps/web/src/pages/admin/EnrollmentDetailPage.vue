<template>
  <q-page class="q-pa-md" v-if="enrollment">
    <div class="row items-center q-mb-md q-gutter-sm">
      <div>
        <div class="text-h5 font-heading text-primary">
          {{ enrollment.couple.partner_one_name }} &amp; {{ enrollment.couple.partner_two_name }}
        </div>
        <div class="text-muted">{{ enrollment.retreat.name }}</div>
      </div>
      <q-space />
      <q-badge :color="enrollment.payment_mode === 'fixed' ? 'primary' : 'secondary'" class="q-px-sm q-py-xs">
        {{ enrollment.payment_mode === 'fixed' ? 'Modalidade fixa' : 'Modalidade flexível' }}
      </q-badge>
      <q-badge v-if="enrollment.status === 'cancelled'" color="negative" class="q-px-sm q-py-xs">Cancelada</q-badge>
    </div>

    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-6 col-md-3">
        <StatCard label="Valor contratado" :value="formatCents(enrollment.total_amount_cents)" icon="request_quote" />
      </div>
      <div class="col-6 col-md-3">
        <StatCard label="Total recebido" :value="formatCents(enrollment.total_paid_cents)" icon="payments" color="positive" />
      </div>
      <div class="col-6 col-md-3">
        <StatCard label="Saldo restante" :value="formatCents(enrollment.balance_cents)" icon="hourglass_bottom" color="warning" />
      </div>
      <div class="col-6 col-md-3">
        <StatCard
          v-if="enrollment.payment_mode === 'flexible'"
          label="Percentual quitado"
          :value="`${enrollment.percent_paid}%`"
          icon="trending_up"
        />
        <StatCard
          v-else
          label="Próximo vencimento"
          :value="enrollment.next_due_installment ? formatDate(enrollment.next_due_installment.due_date) : '—'"
          icon="event"
        />
      </div>
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-7">
        <q-card class="card--soft q-mb-md">
          <q-card-section class="row items-center">
            <div class="text-subtitle1 text-weight-medium">Histórico de pagamentos</div>
            <q-space />
            <q-btn
              v-if="enrollment.status === 'active'"
              color="primary"
              unelevated
              icon="add"
              label="Registrar pagamento"
              @click="showPaymentDialog = true"
            />
          </q-card-section>
          <q-list separator>
            <q-item v-for="payment in payments" :key="payment.id">
              <q-item-section>
                <q-item-label :class="{ 'text-strike text-muted': payment.reversed_at }">
                  {{ formatCents(payment.amount_cents) }} · {{ methodLabel(payment.payment_method) }}
                </q-item-label>
                <q-item-label caption>
                  Pago em {{ formatDate(payment.paid_at) }}
                  <span v-if="payment.reversed_at"> · Estornado em {{ formatDate(payment.reversed_at) }} ({{ payment.reversal_reason }})</span>
                </q-item-label>
              </q-item-section>
              <q-item-section side v-if="!payment.reversed_at">
                <q-btn flat dense size="sm" color="negative" label="Estornar" @click="openReverseDialog(payment)" />
              </q-item-section>
            </q-item>
            <q-item v-if="payments.length === 0">
              <q-item-section class="text-muted">Nenhum pagamento registrado ainda.</q-item-section>
            </q-item>
          </q-list>
        </q-card>

        <q-card v-if="enrollment.payment_mode === 'fixed'" class="card--soft">
          <q-card-section class="row items-center">
            <div class="text-subtitle1 text-weight-medium">Parcelas</div>
            <q-space />
            <q-btn outline color="primary" icon="event_repeat" label="Regerar vencimentos" @click="openRegeneration" />
          </q-card-section>
          <q-list separator>
            <q-item v-for="installment in enrollment.installments" :key="installment.installment_number">
              <q-item-section>
                <q-item-label>Parcela {{ installment.installment_number }} — {{ formatCents(installment.amount_cents) }}</q-item-label>
                <q-item-label caption>Vencimento {{ formatDate(installment.due_date) }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-badge :color="installmentStatusColor(installment.status)">
                  {{ installmentStatusLabel(installment.status) }}
                </q-badge>
                <q-btn flat dense size="sm" label="Editar parcela" @click="openInstallmentDialog(installment)" />
              </q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>

      <div class="col-12 col-md-5">
        <q-card class="card--soft">
          <q-card-section>
            <div class="text-subtitle1 text-weight-medium">Link privado do portal</div>
          </q-card-section>
          <q-card-section>
            <div v-if="freshLink" class="q-mb-sm">
              <q-input :model-value="freshLink" outlined readonly dense>
                <template #append>
                  <q-icon name="content_copy" class="cursor-pointer" @click="copyLink" />
                </template>
              </q-input>
              <div class="text-caption text-muted q-mt-xs">
                Este link só é exibido agora. Copie e envie ao casal.
              </div>
            </div>
            <div v-else class="text-muted q-mb-sm">
              {{ accessLink?.has_active_token ? 'Link ativo (não é possível visualizar novamente; gere um novo se necessário).' : 'Nenhum link ativo.' }}
            </div>

            <div class="q-gutter-sm">
              <q-btn
                color="primary"
                outline
                :label="accessLink?.has_active_token ? 'Regenerar link' : 'Gerar link'"
                @click="confirmRegenerateLink = true"
              />
              <q-btn
                v-if="accessLink?.has_active_token"
                color="negative"
                outline
                label="Revogar"
                @click="confirmRevoke = true"
              />
              <q-btn
                v-if="freshLink"
                color="positive"
                unelevated
                icon="share"
                label="WhatsApp"
                @click="shareOnWhatsApp"
              />
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Dialog: registrar pagamento -->
    <q-dialog v-model="showPaymentDialog">
      <q-card style="min-width: 360px;">
        <q-card-section class="text-h6">Registrar pagamento</q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model.number="paymentForm.amount_reais" outlined type="number" step="0.01" label="Valor (R$)" />
          <q-input v-model="paymentForm.paid_at" outlined type="date" label="Data do pagamento" />
          <q-select
            v-model="paymentForm.payment_method"
            outlined
            label="Forma de pagamento"
            :options="methodOptions"
            emit-value
            map-options
          />
          <q-input v-model="paymentForm.reference" outlined label="Referência (opcional)" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="primary" unelevated label="Confirmar" :loading="savingPayment" @click="submitPayment" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Dialog: estornar pagamento -->
    <q-dialog v-model="showReverseDialog">
      <q-card style="min-width: 360px;">
        <q-card-section class="text-h6">Estornar pagamento</q-card-section>
        <q-card-section>
          <q-input v-model="reverseReason" outlined type="textarea" label="Motivo do estorno *" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="negative" unelevated label="Estornar" :loading="savingReverse" @click="submitReverse" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showInstallmentDialog">
      <q-card style="min-width: 400px;">
        <q-card-section class="text-h6">Editar parcela {{ installmentForm.installment_number }}</q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model="installmentForm.due_date" outlined type="date" label="Vencimento *" />
          <q-input v-model.number="installmentForm.amount_reais" outlined type="number" min="0.01" step="0.01" label="Valor (R$)" :disable="installmentForm.status === 'paid'" />
          <q-select v-if="installmentAmountChanged" v-model="installmentForm.compensating_installment_id" outlined label="Compensar diferença na parcela *" :options="compensationOptions" emit-value map-options />
          <div v-if="installmentForm.status === 'paid'" class="text-caption text-warning">Parcela quitada: apenas o vencimento pode ser corrigido.</div>
        </q-card-section>
        <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup /><q-btn color="primary" label="Salvar" :loading="savingInstallment" @click="submitInstallment" /></q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showRegenerationDialog">
      <q-card style="min-width: 520px;">
        <q-card-section class="text-h6">Regerar vencimentos pendentes</q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model="regenerationForm.first_due_date" outlined type="date" label="Nova data inicial *" />
          <q-select v-model="regenerationForm.periodicity" outlined label="Periodicidade" :options="periodicityOptions" emit-value map-options />
          <q-btn outline color="primary" label="Visualizar comparação" :loading="loadingRegeneration" @click="previewRegeneration" />
          <q-list v-if="regenerationPreview.length" bordered separator>
            <q-item v-for="item in regenerationPreview" :key="item.id"><q-item-section>Parcela {{ item.installment_number }}</q-item-section><q-item-section side>{{ formatDate(item.old_due_date) }} → {{ formatDate(item.new_due_date) }}</q-item-section></q-item>
          </q-list>
        </q-card-section>
        <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup /><q-btn color="primary" label="Confirmar alteração" :disable="!regenerationPreview.length" :loading="savingRegeneration" @click="confirmDueDateRegeneration" /></q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="confirmRegenerateLink">
      <q-card>
        <q-card-section class="text-h6">Gerar novo link?</q-card-section>
        <q-card-section>
          Se já existir um link ativo, ele será revogado imediatamente e deixará de funcionar.
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="primary" unelevated label="Confirmar" @click="regenerateLink" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="confirmRevoke">
      <q-card>
        <q-card-section class="text-h6">Revogar link?</q-card-section>
        <q-card-section>O casal perderá o acesso ao portal imediatamente.</q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="negative" unelevated label="Revogar" @click="revokeLink" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useQuasar } from 'quasar';
import { api } from '@/boot/axios';
import StatCard from '@/components/StatCard.vue';
import {
  formatCents,
  formatDate,
  methodLabel,
  installmentStatusLabel,
  installmentStatusColor,
  normalizePhoneForWhatsApp,
} from '@/utils/format';

interface Installment { id: number; installment_number: number; amount_cents: number; paid_cents: number; due_date: string; status: string }
interface Enrollment {
  id: number;
  payment_mode: 'fixed' | 'flexible';
  status: string;
  total_amount_cents: number;
  total_paid_cents: number;
  balance_cents: number;
  percent_paid: number;
  couple: { partner_one_name: string; partner_two_name: string; phone: string };
  retreat: { name: string };
  next_due_installment: { due_date: string } | null;
  installments: Installment[];
}

interface Payment {
  id: number;
  amount_cents: number;
  paid_at: string;
  payment_method: string;
  reversed_at: string | null;
  reversal_reason: string | null;
}

const route = useRoute();
const $q = useQuasar();

const enrollment = ref<Enrollment | null>(null);
const payments = ref<Payment[]>([]);
const accessLink = ref<{ has_active_token: boolean } | null>(null);
const freshLink = ref<string | null>(null);

const showPaymentDialog = ref(false);
const savingPayment = ref(false);
const showReverseDialog = ref(false);
const savingReverse = ref(false);
const confirmRegenerateLink = ref(false);
const confirmRevoke = ref(false);
const reverseReason = ref('');
const paymentToReverse = ref<Payment | null>(null);
const showInstallmentDialog = ref(false);
const savingInstallment = ref(false);
const originalInstallmentAmount = ref(0);
const installmentForm = reactive({ id: 0, installment_number: 0, due_date: '', amount_reais: 0, status: '', compensating_installment_id: null as number | null });
const installmentAmountChanged = computed(() => Math.round(installmentForm.amount_reais * 100) !== originalInstallmentAmount.value);
const compensationOptions = computed(() => (enrollment.value?.installments ?? []).filter((item) => item.id !== installmentForm.id && item.status !== 'paid').map((item) => ({ label: `Parcela ${item.installment_number} — ${formatCents(item.amount_cents)}`, value: item.id })));
const showRegenerationDialog = ref(false);
const loadingRegeneration = ref(false);
const savingRegeneration = ref(false);
const regenerationForm = reactive({ first_due_date: '', periodicity: 'monthly' });
const regenerationPreview = ref<Array<{ id: number; installment_number: number; old_due_date: string; new_due_date: string }>>([]);
const periodicityOptions = [{ label: 'Mensal', value: 'monthly' }, { label: 'Semanal', value: 'weekly' }];

const methodOptions = [
  { label: 'Pix', value: 'pix' },
  { label: 'Dinheiro', value: 'cash' },
  { label: 'Transferência', value: 'transfer' },
  { label: 'Outro', value: 'other' },
];

const paymentForm = reactive({
  amount_reais: 0,
  paid_at: new Date().toISOString().slice(0, 10),
  payment_method: 'pix',
  reference: '',
});

async function loadAll() {
  const id = route.params.id;
  const [enrollmentResponse, paymentsResponse, linkResponse] = await Promise.all([
    api.get(`/enrollments/${id}`),
    api.get(`/enrollments/${id}/payments`),
    api.get(`/enrollments/${id}/access-link`),
  ]);
  enrollment.value = enrollmentResponse.data.data;
  payments.value = paymentsResponse.data.data;
  accessLink.value = linkResponse.data.data;
}

onMounted(loadAll);

async function submitPayment() {
  savingPayment.value = true;
  try {
    await api.post(`/enrollments/${route.params.id}/payments`, {
      amount_cents: Math.round(paymentForm.amount_reais * 100),
      paid_at: paymentForm.paid_at,
      payment_method: paymentForm.payment_method,
      reference: paymentForm.reference || undefined,
    });
    $q.notify({ type: 'positive', message: 'Pagamento registrado com sucesso.' });
    showPaymentDialog.value = false;
    await loadAll();
  } catch (err) {
    const message =
      (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
      'Não foi possível registrar o pagamento.';
    $q.notify({ type: 'negative', message });
  } finally {
    savingPayment.value = false;
  }
}

function openReverseDialog(payment: Payment) {
  paymentToReverse.value = payment;
  reverseReason.value = '';
  showReverseDialog.value = true;
}

async function submitReverse() {
  if (!paymentToReverse.value) return;
  savingReverse.value = true;
  try {
    await api.post(`/payments/${paymentToReverse.value.id}/reverse`, { reason: reverseReason.value });
    $q.notify({ type: 'positive', message: 'Pagamento estornado.' });
    showReverseDialog.value = false;
    await loadAll();
  } catch {
    $q.notify({ type: 'negative', message: 'Não foi possível estornar o pagamento.' });
  } finally {
    savingReverse.value = false;
  }
}

function openInstallmentDialog(item: Installment) {
  Object.assign(installmentForm, { id: item.id, installment_number: item.installment_number, due_date: item.due_date, amount_reais: item.amount_cents / 100, status: item.status, compensating_installment_id: null });
  originalInstallmentAmount.value = item.amount_cents;
  showInstallmentDialog.value = true;
}

async function submitInstallment() {
  savingInstallment.value = true;
  try {
    const payload: Record<string, unknown> = { due_date: installmentForm.due_date };
    if (installmentAmountChanged.value) {
      payload.amount_cents = Math.round(installmentForm.amount_reais * 100);
      payload.compensating_installment_id = installmentForm.compensating_installment_id;
    }
    await api.patch(`/installments/${installmentForm.id}`, payload);
    showInstallmentDialog.value = false;
    $q.notify({ type: 'positive', message: 'Parcela atualizada.' });
    await loadAll();
  } catch (err) {
    $q.notify({ type: 'negative', message: (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Não foi possível atualizar a parcela.' });
  } finally { savingInstallment.value = false; }
}

function openRegeneration() {
  regenerationForm.first_due_date = '';
  regenerationPreview.value = [];
  showRegenerationDialog.value = true;
}

async function previewRegeneration() {
  loadingRegeneration.value = true;
  try {
    const response = await api.post(`/enrollments/${route.params.id}/installments/regenerate`, regenerationForm);
    regenerationPreview.value = response.data.data;
  } catch (err) {
    $q.notify({ type: 'negative', message: (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Não foi possível calcular os vencimentos.' });
  } finally { loadingRegeneration.value = false; }
}

async function confirmDueDateRegeneration() {
  savingRegeneration.value = true;
  try {
    await api.post(`/enrollments/${route.params.id}/installments/regenerate`, { ...regenerationForm, confirm: true });
    showRegenerationDialog.value = false;
    $q.notify({ type: 'positive', message: 'Vencimentos atualizados.' });
    await loadAll();
  } finally { savingRegeneration.value = false; }
}

async function regenerateLink() {
  const response = await api.post(`/enrollments/${route.params.id}/access-link/regenerate`);
  freshLink.value = `${window.location.origin}${response.data.data.portal_path}`;
  await loadAll();
}

async function revokeLink() {
  await api.post(`/enrollments/${route.params.id}/access-link/revoke`);
  freshLink.value = null;
  $q.notify({ type: 'positive', message: 'Link revogado.' });
  await loadAll();
}

function copyLink() {
  if (!freshLink.value) return;
  void navigator.clipboard.writeText(freshLink.value);
  $q.notify({ type: 'positive', message: 'Link copiado.' });
}

function shareOnWhatsApp() {
  if (!freshLink.value || !enrollment.value) return;
  const phone = normalizePhoneForWhatsApp(enrollment.value.couple.phone);
  const message = `Olá! Segue o link privado de acompanhamento da sua inscrição no retiro: ${freshLink.value}`;
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
}
</script>
