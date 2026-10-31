<template>
  <q-page class="q-pa-md" style="max-width: 560px; margin: 0 auto;">
    <div v-if="loading" class="column items-center q-mt-xl">
      <q-spinner color="primary" size="48px" />
    </div>

    <div v-else-if="invalid" class="column items-center text-center q-mt-xl q-px-md">
      <q-icon name="link_off" size="64px" color="grey-6" />
      <div class="text-h6 q-mt-md">Link indisponível</div>
      <div class="text-muted q-mt-sm">
        Este link não é mais válido. Ele pode ter expirado, sido revogado, ou o endereço está incorreto.
        Entre em contato com a organização do retiro para receber um novo link.
      </div>
    </div>

    <template v-else-if="data">
      <q-img
        v-if="data.retreat.cover_image_url && !coverImageFailed"
        :src="data.retreat.cover_image_url"
        :alt="`Capa do ${data.retreat.name}`"
        :ratio="16 / 9"
        class="rounded-borders q-mb-md bg-grey-2"
        fit="contain"
        @error="handleCoverImageError"
      />

      <div class="text-center q-mb-md">
        <div class="text-h6 font-heading text-primary">
          Olá, {{ data.couple.partner_one_name }} &amp; {{ data.couple.partner_two_name }}!
        </div>
        <div class="text-muted">{{ data.retreat.name }}</div>
        <div v-if="data.retreat.start_date" class="text-caption text-muted">
          {{ formatDate(data.retreat.start_date) }} – {{ formatDate(data.retreat.end_date) }}
          <span v-if="data.retreat.location"> · {{ data.retreat.location }}</span>
        </div>
      </div>

      <q-card class="card--soft q-mb-md">
        <q-card-section>
          <div class="row justify-between text-subtitle2 text-muted">
            <span>Valor contratado</span>
            <span class="text-weight-bold text-dark">{{ formatCents(data.total_amount_cents) }}</span>
          </div>
          <div class="row justify-between text-subtitle2 text-muted q-mt-xs">
            <span>Total pago</span>
            <span class="text-weight-bold text-positive">{{ formatCents(data.total_paid_cents) }}</span>
          </div>
          <div class="row justify-between text-subtitle2 text-muted q-mt-xs">
            <span>Saldo restante</span>
            <span class="text-weight-bold text-warning">{{ formatCents(data.balance_cents) }}</span>
          </div>

          <q-linear-progress
            :value="progressValue"
            color="positive"
            track-color="grey-3"
            rounded
            size="12px"
            class="q-mt-md"
          />
          <div class="text-caption text-muted q-mt-xs text-right">
            {{ progressLabel }}
          </div>
        </q-card-section>
      </q-card>

      <q-card v-if="data.payment_mode === 'fixed' && data.next_due_installment" class="card--soft q-mb-md bg-cream">
        <q-card-section class="row items-center">
          <q-icon name="event" color="primary" size="28px" class="q-mr-sm" />
          <div>
            <div class="text-caption text-muted">Próximo vencimento</div>
            <div class="text-weight-bold">
              {{ formatDate(data.next_due_installment.due_date) }} — {{ formatCents(data.next_due_installment.balance_cents) }}
            </div>
          </div>
        </q-card-section>
      </q-card>

      <q-card v-if="data.payment_mode === 'fixed'" class="card--soft q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium">Parcelas</div>
        </q-card-section>
        <q-list separator>
          <q-item v-for="installment in data.installments" :key="installment.installment_number">
            <q-item-section>
              <q-item-label>Parcela {{ installment.installment_number }}</q-item-label>
              <q-item-label caption>Vencimento {{ formatDate(installment.due_date) }}</q-item-label>
            </q-item-section>
            <q-item-section side>
              <div class="text-weight-medium">{{ formatCents(installment.amount_cents) }}</div>
              <q-badge :color="installmentStatusColor(installment.status)">{{ installmentStatusLabel(installment.status) }}</q-badge>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card>

      <q-card v-if="data.retreat.pix_key || data.retreat.receipt_whatsapp" class="card--soft q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium">Pagamento</div>
          <div v-if="data.retreat.pix_key" class="q-mt-sm">
            <div class="text-caption text-muted">Chave PIX{{ data.retreat.pix_beneficiary_name ? ` · ${data.retreat.pix_beneficiary_name}` : '' }}</div>
            <div class="row items-center no-wrap q-gutter-sm">
              <div class="text-weight-medium ellipsis">{{ data.retreat.pix_key }}</div>
              <q-btn flat dense icon="content_copy" label="Copiar" color="primary" @click="copyPixKey" />
            </div>
          </div>
          <q-btn
            v-if="data.retreat.receipt_whatsapp"
            class="q-mt-md"
            color="positive"
            icon="chat"
            label="Enviar comprovante pelo WhatsApp"
            @click="sendReceiptByWhatsApp"
          />
          <div v-if="data.retreat.receipt_whatsapp" class="text-caption text-muted q-mt-xs">O comprovante será enviado manualmente. O portal não registra pagamentos automaticamente.</div>
        </q-card-section>
      </q-card>

      <q-card class="card--soft q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium">Pagamentos recebidos</div>
        </q-card-section>
        <q-list separator>
          <q-item v-for="(payment, idx) in data.payments" :key="idx">
            <q-item-section>
              <q-item-label>{{ formatCents(payment.amount_cents) }}</q-item-label>
              <q-item-label caption>{{ formatDate(payment.paid_at) }} · {{ methodLabel(payment.payment_method) }}</q-item-label>
            </q-item-section>
          </q-item>
          <q-item v-if="data.payments.length === 0">
            <q-item-section class="text-muted">Nenhum pagamento registrado ainda.</q-item-section>
          </q-item>
        </q-list>
      </q-card>

      <q-card v-if="announcements.length > 0" class="card--soft">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium">Avisos</div>
        </q-card-section>
        <q-list separator>
          <q-item v-for="(announcement, idx) in announcements" :key="idx">
            <q-item-section>
              <q-item-label class="text-weight-medium">{{ announcement.title }}</q-item-label>
              <q-item-label caption lines="4" style="white-space: pre-line;">{{ announcement.content }}</q-item-label>
              <q-item-label caption>{{ formatDate(announcement.published_at) }}</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card>
    </template>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useQuasar } from 'quasar';
import axios from 'axios';
import { formatCents, formatDate, methodLabel, installmentStatusLabel, installmentStatusColor, normalizePhoneForWhatsApp } from '@/utils/format';

interface PortalData {
  couple: { partner_one_name: string; partner_two_name: string };
  retreat: {
    name: string;
    start_date: string | null;
    end_date: string | null;
    location: string | null;
    cover_image_url: string | null;
    pix_key_type: string | null;
    pix_key: string | null;
    pix_beneficiary_name: string | null;
    receipt_whatsapp: string | null;
  };
  payment_mode: 'fixed' | 'flexible';
  total_amount_cents: number;
  total_paid_cents: number;
  balance_cents: number;
  percent_paid: number | null;
  next_due_installment: { installment_number: number; due_date: string; balance_cents: number } | null;
  installments: Array<{ installment_number: number; amount_cents: number; due_date: string; status: string }> | null;
  payments: Array<{ amount_cents: number; paid_at: string; payment_method: string }>;
}

interface Announcement {
  title: string;
  content: string;
  published_at: string;
}

const route = useRoute();
const $q = useQuasar();
const loading = ref(true);
const invalid = ref(false);
const data = ref<PortalData | null>(null);
const announcements = ref<Announcement[]>([]);
const coverImageFailed = ref(false);

const progressValue = computed(() => {
  if (!data.value || data.value.total_amount_cents === 0) return 0;
  return Math.min(1, data.value.total_paid_cents / data.value.total_amount_cents);
});

const progressLabel = computed(() => {
  if (!data.value) return '';
  const pct = Math.round(progressValue.value * 100);
  return `${pct}% quitado`;
});

async function copyPixKey() {
  if (!data.value?.retreat.pix_key) return;
  await navigator.clipboard.writeText(data.value.retreat.pix_key);
  $q.notify({ type: 'positive', message: 'Chave PIX copiada.' });
}

function sendReceiptByWhatsApp() {
  if (!data.value?.retreat.receipt_whatsapp) return;
  const phone = normalizePhoneForWhatsApp(data.value.retreat.receipt_whatsapp);
  const coupleName = `${data.value.couple.partner_one_name} e ${data.value.couple.partner_two_name}`;
  const message = `Olá! Somos ${coupleName}. Segue o comprovante referente ao ${data.value.retreat.name}.`;
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank', 'noopener,noreferrer');
}

function handleCoverImageError() {
  coverImageFailed.value = true;
  console.warn('Não foi possível carregar a capa do retiro no portal.', {
    retreat: data.value?.retreat.name,
  });
}

onMounted(async () => {
  const token = route.params.token as string;

  try {
    const [portalResponse, announcementsResponse] = await Promise.all([
      axios.get(`/api/v1/public/portal/${token}`, { withCredentials: true }),
      axios.get(`/api/v1/public/portal/${token}/announcements`, { withCredentials: true }),
    ]);

    data.value = portalResponse.data.data;
    announcements.value = announcementsResponse.data.data;
  } catch {
    invalid.value = true;
  } finally {
    loading.value = false;
  }
});
</script>
