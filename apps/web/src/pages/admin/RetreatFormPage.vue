<template>
  <q-page class="q-pa-md">
    <div class="text-h5 font-heading text-primary q-mb-md">
      {{ isEditing ? 'Editar retiro' : 'Novo retiro' }}
    </div>

    <q-card class="card--soft q-pa-md" style="max-width: 760px;">
      <q-form class="q-gutter-md" @submit.prevent="handleSubmit">
        <div class="text-subtitle1 text-weight-medium">Informações gerais</div>
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

        <q-separator />
        <div class="text-subtitle1 text-weight-medium">Identidade visual</div>
        <q-img v-if="coverPreviewUrl" :src="coverPreviewUrl" :ratio="16 / 9" fit="contain" class="rounded-borders bg-grey-2" />
        <div v-else class="cover-placeholder rounded-borders column items-center justify-center text-grey-7">
          <q-icon name="image_not_supported" size="40px" />
          <div class="q-mt-sm">Nenhuma imagem de capa</div>
        </div>
        <q-file
          v-model="coverFile"
          outlined
          accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
          max-file-size="5242880"
          label="Imagem de capa (JPG, PNG ou WebP, até 5 MB)"
          @rejected="notifyRejectedFile"
        >
          <template #prepend><q-icon name="image" /></template>
        </q-file>
        <q-btn v-if="coverPreviewUrl" flat color="negative" icon="delete" label="Remover imagem" @click="removeCover" />

        <q-separator />
        <div class="text-subtitle1 text-weight-medium">Informações de pagamento</div>
        <q-select v-model="form.pix_key_type" outlined clearable label="Tipo da chave PIX" :options="pixKeyTypeOptions" emit-value map-options />
        <q-input v-model="form.pix_key" outlined label="Chave PIX" />
        <q-input v-model="form.pix_beneficiary_name" outlined label="Nome do favorecido" />
        <q-input v-model="form.receipt_whatsapp" outlined label="WhatsApp para recebimento dos comprovantes" hint="Informe o DDD e o número" />

        <div class="row justify-end q-gutter-sm">
          <q-btn flat label="Cancelar" to="/app/retreats" />
          <q-btn type="submit" color="primary" unelevated label="Salvar" :loading="saving" />
        </div>
      </q-form>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { api } from '@/boot/axios';

const route = useRoute();
const router = useRouter();
const $q = useQuasar();

const isEditing = computed(() => route.params.id !== undefined);
const saving = ref(false);
const coverFile = ref<File | null>(null);
const storedCoverUrl = ref<string | null>(null);
const removeStoredCover = ref(false);
const objectUrl = ref<string | null>(null);

const form = reactive({
  name: '',
  description: '',
  start_date: '',
  end_date: '',
  location: '',
  status: 'draft',
  registration_open: true,
  pix_key_type: null as string | null,
  pix_key: '',
  pix_beneficiary_name: '',
  receipt_whatsapp: '',
});

const pixKeyTypeOptions = [
  { label: 'CPF', value: 'cpf' },
  { label: 'CNPJ', value: 'cnpj' },
  { label: 'E-mail', value: 'email' },
  { label: 'Telefone', value: 'phone' },
  { label: 'Chave aleatória', value: 'random' },
];

const coverPreviewUrl = computed(() => objectUrl.value || (removeStoredCover.value ? null : storedCoverUrl.value));

watch(coverFile, (file) => {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);
  objectUrl.value = file ? URL.createObjectURL(file) : null;
  if (file) removeStoredCover.value = false;
});

onBeforeUnmount(() => {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);
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
    form.pix_key_type = retreat.pix_key_type;
    form.pix_key = retreat.pix_key ?? '';
    form.pix_beneficiary_name = retreat.pix_beneficiary_name ?? '';
    form.receipt_whatsapp = retreat.receipt_whatsapp ?? '';
    storedCoverUrl.value = retreat.cover_image_url;
  }
});

function removeCover() {
  coverFile.value = null;
  removeStoredCover.value = true;
}

function notifyRejectedFile() {
  $q.notify({ type: 'negative', message: 'Selecione uma imagem JPG, PNG ou WebP com no máximo 5 MB.' });
}

async function handleSubmit() {
  saving.value = true;
  try {
    const payload = new FormData();
    Object.entries(form).forEach(([key, value]) => {
      if (typeof value === 'boolean') payload.append(key, value ? '1' : '0');
      else if (value !== null) payload.append(key, value);
    });
    if (coverFile.value) payload.append('cover_image', coverFile.value);
    if (removeStoredCover.value) payload.append('remove_cover_image', '1');

    if (isEditing.value) {
      payload.append('_method', 'PATCH');
      await api.post(`/retreats/${route.params.id}`, payload);
    } else {
      await api.post('/retreats', payload);
    }
    $q.notify({ type: 'positive', message: 'Retiro salvo com sucesso.' });
    await router.push('/app/retreats');
  } catch (err) {
    const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Não foi possível salvar o retiro.';
    $q.notify({ type: 'negative', message });
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.cover-placeholder {
  aspect-ratio: 16 / 9;
  width: 100%;
  background: #f1f1f1;
  border: 1px dashed #bdbdbd;
}
</style>
