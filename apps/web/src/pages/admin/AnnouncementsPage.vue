<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md q-gutter-sm">
      <div class="text-h5 font-heading text-primary">Avisos</div>
      <q-space />
      <q-select
        v-model="retreatFilter"
        outlined
        dense
        emit-value
        map-options
        :options="retreatOptions"
        label="Retiro"
        style="min-width: 220px;"
        @update:model-value="load"
      />
      <q-btn color="primary" icon="add" label="Novo aviso" unelevated @click="openCreate" />
    </div>

    <q-list class="card--soft" separator bordered>
      <q-item v-for="announcement in announcements" :key="announcement.id">
        <q-item-section>
          <q-item-label class="text-weight-medium">{{ announcement.title }}</q-item-label>
          <q-item-label caption lines="2">{{ announcement.content }}</q-item-label>
          <q-item-label caption>
            <q-badge :color="announcement.is_published ? 'positive' : 'grey-6'">
              {{ announcement.is_published ? 'Publicado' : 'Rascunho' }}
            </q-badge>
            <span v-if="announcement.visible_until"> · válido até {{ formatDate(announcement.visible_until) }}</span>
          </q-item-label>
        </q-item-section>
        <q-item-section side top>
          <q-btn
            flat
            dense
            size="sm"
            :label="announcement.published_at ? 'Despublicar' : 'Publicar'"
            @click="togglePublish(announcement)"
          />
        </q-item-section>
      </q-item>
      <q-item v-if="announcements.length === 0">
        <q-item-section class="text-muted">Nenhum aviso para este retiro ainda.</q-item-section>
      </q-item>
    </q-list>

    <q-dialog v-model="showDialog">
      <q-card style="min-width: 420px;">
        <q-card-section class="text-h6">Novo aviso</q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model="form.title" outlined label="Título *" />
          <q-input v-model="form.content" outlined type="textarea" label="Conteúdo *" />
          <q-input v-model="form.visible_until" outlined type="date" label="Válido até (opcional)" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="primary" unelevated label="Salvar" :loading="saving" @click="submit" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useQuasar } from 'quasar';
import { api } from '@/boot/axios';
import { formatDate } from '@/utils/format';

interface Announcement {
  id: number;
  title: string;
  content: string;
  published_at: string | null;
  visible_until: string | null;
  is_published: boolean;
}

const $q = useQuasar();
const retreatOptions = ref<Array<{ label: string; value: number }>>([]);
const retreatFilter = ref<number | null>(null);
const announcements = ref<Announcement[]>([]);
const showDialog = ref(false);
const saving = ref(false);

const form = reactive({ title: '', content: '', visible_until: '' });

function openCreate() {
  form.title = '';
  form.content = '';
  form.visible_until = '';
  showDialog.value = true;
}

async function load() {
  if (!retreatFilter.value) return;
  const response = await api.get('/announcements', { params: { retreat_id: retreatFilter.value, per_page: 50 } });
  announcements.value = response.data.data;
}

async function submit() {
  saving.value = true;
  try {
    await api.post('/announcements', {
      retreat_id: retreatFilter.value,
      title: form.title,
      content: form.content,
      visible_until: form.visible_until || undefined,
    });
    $q.notify({ type: 'positive', message: 'Aviso criado.' });
    showDialog.value = false;
    await load();
  } catch {
    $q.notify({ type: 'negative', message: 'Não foi possível salvar o aviso.' });
  } finally {
    saving.value = false;
  }
}

async function togglePublish(announcement: Announcement) {
  const action = announcement.published_at ? 'unpublish' : 'publish';
  await api.post(`/announcements/${announcement.id}/${action}`);
  await load();
}

onMounted(async () => {
  const response = await api.get('/retreats', { params: { per_page: 100 } });
  retreatOptions.value = response.data.data.map((r: { id: number; name: string }) => ({ label: r.name, value: r.id }));
  if (retreatOptions.value.length > 0) {
    retreatFilter.value = retreatOptions.value[0]?.value ?? null;
    await load();
  }
});
</script>
