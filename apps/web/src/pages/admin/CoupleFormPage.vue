<template>
  <q-page class="q-pa-md">
    <div class="text-h5 font-heading text-primary q-mb-md">
      {{ isEditing ? 'Editar casal' : 'Novo casal' }}
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-6">
        <q-card class="card--soft q-pa-md">
          <q-form class="q-gutter-md" @submit.prevent="handleSubmit">
            <q-input v-model="form.partner_one_name" outlined label="Nome do(a) parceiro(a) 1 *" :rules="[required]" />
            <q-input v-model="form.partner_two_name" outlined label="Nome do(a) parceiro(a) 2 *" :rules="[required]" />
            <q-input v-model="form.phone" outlined label="Telefone *" hint="Formato internacional, ex: +55 11 91234-5678" :rules="[required]" />
            <q-input v-model="form.secondary_phone" outlined label="Telefone secundário" />
            <q-input v-model="form.email" outlined type="email" label="E-mail" />
            <q-input v-model="form.notes" outlined type="textarea" label="Observações administrativas" hint="Visível apenas para a equipe, nunca para o casal" />

            <div class="row justify-end q-gutter-sm">
              <q-btn flat label="Cancelar" to="/app/couples" />
              <q-btn type="submit" color="primary" unelevated label="Salvar" :loading="saving" />
            </div>
          </q-form>
        </q-card>
      </div>

      <div v-if="isEditing" class="col-12 col-md-6">
        <q-card class="card--soft">
          <q-card-section class="row items-center">
            <div class="text-subtitle1 text-weight-medium">Inscrições</div>
            <q-space />
            <q-btn
              size="sm"
              color="primary"
              flat
              icon="add"
              label="Inscrever"
              @click="router.push({ path: '/app/enrollments/new', query: { couple_id: route.params.id } })"
            />
          </q-card-section>
          <q-list separator>
            <q-item
              v-for="enrollment in enrollments"
              :key="enrollment.id"
              clickable
              @click="router.push(`/app/enrollments/${enrollment.id}`)"
            >
              <q-item-section>
                <q-item-label>{{ enrollment.retreat.name }}</q-item-label>
                <q-item-label caption>{{ enrollment.payment_mode === 'fixed' ? 'Fixa' : 'Flexível' }}</q-item-label>
              </q-item-section>
              <q-item-section side>{{ formatCents(enrollment.balance_cents) }} restante</q-item-section>
            </q-item>
            <q-item v-if="enrollments.length === 0">
              <q-item-section class="text-muted">Nenhuma inscrição ainda.</q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>
    </div>
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

const isEditing = computed(() => route.params.id !== undefined);
const saving = ref(false);

interface EnrollmentSummary {
  id: number;
  payment_mode: string;
  balance_cents: number;
  retreat: { name: string };
}
const enrollments = ref<EnrollmentSummary[]>([]);

const form = reactive({
  partner_one_name: '',
  partner_two_name: '',
  phone: '',
  secondary_phone: '',
  email: '',
  notes: '',
});

function required(val: string) {
  return !!val || 'Campo obrigatório';
}

onMounted(async () => {
  if (isEditing.value) {
    const response = await api.get(`/couples/${route.params.id}`);
    Object.assign(form, response.data.data);

    const enrollmentsResponse = await api.get('/enrollments', {
      params: { couple_id: route.params.id, per_page: 50 },
    });
    enrollments.value = enrollmentsResponse.data.data;
  }
});

async function handleSubmit() {
  saving.value = true;
  try {
    if (isEditing.value) {
      await api.patch(`/couples/${route.params.id}`, form);
    } else {
      const response = await api.post('/couples', form);
      $q.notify({ type: 'positive', message: 'Casal cadastrado com sucesso.' });
      await router.push(`/app/couples/${response.data.data.id}`);
      return;
    }
    $q.notify({ type: 'positive', message: 'Casal atualizado com sucesso.' });
  } catch {
    $q.notify({ type: 'negative', message: 'Não foi possível salvar o casal.' });
  } finally {
    saving.value = false;
  }
}
</script>
