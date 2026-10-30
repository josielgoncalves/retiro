<template>
  <q-page class="q-pa-md">
    <div class="text-h5 font-heading text-primary q-mb-md">
      {{ isEditing ? 'Editar retiro' : 'Novo retiro' }}
    </div>

    <q-card class="card--soft q-pa-md" style="max-width: 640px;">
      <q-form class="q-gutter-md" @submit.prevent="handleSubmit">
        <q-input v-model="form.name" outlined label="Nome do retiro *" :rules="[required]" />
        <q-input v-model="form.description" outlined type="textarea" label="Descrição" />

        <div class="row q-col-gutter-md">
          <div class="col-6">
            <q-input v-model="form.start_date" outlined label="Data de início *" type="date" :rules="[required]" />
          </div>
          <div class="col-6">
            <q-input v-model="form.end_date" outlined label="Data de término *" type="date" :rules="[required]" />
          </div>
        </div>

        <q-input v-model="form.location" outlined label="Local" />

        <q-select
          v-model="form.status"
          outlined
          label="Situação"
          :options="statusOptions"
          emit-value
          map-options
        />

        <q-toggle v-model="form.registration_open" label="Inscrições abertas" color="primary" />

        <div class="row justify-end q-gutter-sm">
          <q-btn flat label="Cancelar" to="/app/retreats" />
          <q-btn type="submit" color="primary" unelevated label="Salvar" :loading="saving" />
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

const route = useRoute();
const router = useRouter();
const $q = useQuasar();

const isEditing = computed(() => route.params.id !== undefined);
const saving = ref(false);

const form = reactive({
  name: '',
  description: '',
  start_date: '',
  end_date: '',
  location: '',
  status: 'draft',
  registration_open: true,
});

const statusOptions = [
  { label: 'Rascunho', value: 'draft' },
  { label: 'Ativo', value: 'active' },
  { label: 'Finalizado', value: 'finished' },
  { label: 'Arquivado', value: 'archived' },
];

function required(val: string) {
  return !!val || 'Campo obrigatório';
}

onMounted(async () => {
  if (isEditing.value) {
    const response = await api.get(`/retreats/${route.params.id}`);
    const retreat = response.data.data;
    form.name = retreat.name;
    form.description = retreat.description ?? '';
    form.start_date = retreat.start_date;
    form.end_date = retreat.end_date;
    form.location = retreat.location ?? '';
    form.status = retreat.status;
    form.registration_open = retreat.registration_open;
  }
});

async function handleSubmit() {
  saving.value = true;
  try {
    if (isEditing.value) {
      await api.patch(`/retreats/${route.params.id}`, form);
    } else {
      await api.post('/retreats', form);
    }
    $q.notify({ type: 'positive', message: 'Retiro salvo com sucesso.' });
    await router.push('/app/retreats');
  } catch {
    $q.notify({ type: 'negative', message: 'Não foi possível salvar o retiro.' });
  } finally {
    saving.value = false;
  }
}
</script>
