<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5 font-heading text-primary">Usuários administrativos</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Novo usuário" unelevated @click="openCreate" />
    </div>

    <q-table :rows="users" :columns="columns" row-key="id" flat bordered class="card--soft" @row-click="(_, row) => openEdit(row)">
      <template #body-cell-role="props">
        <q-td :props="props">
          <q-badge :color="props.value === 'admin' ? 'primary' : 'secondary'">
            {{ props.value === 'admin' ? 'Administrador' : 'Operador' }}
          </q-badge>
        </q-td>
      </template>
      <template #body-cell-active="props">
        <q-td :props="props">
          <q-badge :color="props.value ? 'positive' : 'grey-6'">{{ props.value ? 'Ativo' : 'Desativado' }}</q-badge>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="showDialog">
      <q-card style="min-width: 380px;">
        <q-card-section class="text-h6">{{ editingId ? 'Editar usuário' : 'Novo usuário' }}</q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model="form.name" outlined label="Nome *" />
          <q-input v-model="form.email" outlined type="email" label="E-mail *" />
          <q-input v-model="form.password" outlined type="password" :label="editingId ? 'Nova senha (opcional)' : 'Senha *'" />
          <q-select
            v-model="form.role"
            outlined
            label="Perfil"
            :options="[{ label: 'Administrador', value: 'admin' }, { label: 'Operador', value: 'operator' }]"
            emit-value
            map-options
          />
          <q-toggle v-model="form.active" label="Ativo" color="primary" />
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

interface UserRow {
  id: number;
  name: string;
  email: string;
  role: string;
  active: boolean;
}

const $q = useQuasar();
const users = ref<UserRow[]>([]);
const showDialog = ref(false);
const saving = ref(false);
const editingId = ref<number | null>(null);

const columns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left' as const },
  { name: 'email', label: 'E-mail', field: 'email', align: 'left' as const },
  { name: 'role', label: 'Perfil', field: 'role', align: 'center' as const },
  { name: 'active', label: 'Situação', field: 'active', align: 'center' as const },
];

const form = reactive({ name: '', email: '', password: '', role: 'operator', active: true });

function openCreate() {
  editingId.value = null;
  Object.assign(form, { name: '', email: '', password: '', role: 'operator', active: true });
  showDialog.value = true;
}

function openEdit(user: UserRow) {
  editingId.value = user.id;
  Object.assign(form, { name: user.name, email: user.email, password: '', role: user.role, active: user.active });
  showDialog.value = true;
}

async function load() {
  const response = await api.get('/users', { params: { per_page: 50 } });
  users.value = response.data.data;
}

async function submit() {
  saving.value = true;
  try {
    if (editingId.value) {
      const payload: Record<string, unknown> = { ...form };
      if (!payload.password) delete payload.password;
      await api.patch(`/users/${editingId.value}`, payload);
    } else {
      await api.post('/users', form);
    }
    $q.notify({ type: 'positive', message: 'Usuário salvo com sucesso.' });
    showDialog.value = false;
    await load();
  } catch {
    $q.notify({ type: 'negative', message: 'Não foi possível salvar o usuário.' });
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
