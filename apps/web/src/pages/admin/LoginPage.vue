<template>
  <div class="flex flex-center" style="background: #264B42; min-height: 100vh; width: 100%;">
    <q-card class="card--soft q-pa-lg" style="width: 100%; max-width: 380px;">
      <q-card-section class="text-center">
        <div class="text-h5 font-heading text-primary">Portal do Retiro</div>
        <div class="text-muted">Acesso administrativo</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit.prevent="handleSubmit" class="q-gutter-md">
          <q-input
            v-model="email"
            outlined
            type="email"
            label="E-mail"
            autocomplete="username"
            :error="!!error"
            lazy-rules
            :rules="[(val) => !!val || 'Informe o e-mail']"
          />
          <q-input
            v-model="password"
            outlined
            :type="showPassword ? 'text' : 'password'"
            label="Senha"
            autocomplete="current-password"
            :error="!!error"
            :error-message="error"
            lazy-rules
            :rules="[(val) => !!val || 'Informe a senha']"
          >
            <template #append>
              <q-icon
                :name="showPassword ? 'visibility_off' : 'visibility'"
                class="cursor-pointer"
                @click="showPassword = !showPassword"
              />
            </template>
          </q-input>

          <q-btn
            type="submit"
            color="primary"
            label="Entrar"
            class="full-width"
            :loading="loading"
            unelevated
            size="lg"
          />
        </q-form>
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { AxiosError } from 'axios';

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const loading = ref(false);
const error = ref('');

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

async function handleSubmit() {
  error.value = '';
  loading.value = true;

  try {
    await auth.login(email.value, password.value);
    const redirect = (route.query.redirect as string) || '/app/dashboard';
    await router.push(redirect);
  } catch (err) {
    if (err instanceof AxiosError && err.response?.status === 429) {
      error.value = 'Muitas tentativas. Aguarde um momento antes de tentar novamente.';
    } else {
      error.value = 'E-mail ou senha inválidos.';
    }
  } finally {
    loading.value = false;
  }
}
</script>
