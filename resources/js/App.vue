<template>
  <main v-if="user">
    <h1>Nuvads</h1>
    <p
      v-if="error"
      role="alert"
    >
      {{ error }}
    </p>

    <section>
      <h2>Hola, {{ user.name }}</h2>
      <p>{{ user.email }}</p>
      <p>Identificador de tu cuenta: <strong>{{ user.login_identifier }}</strong></p>

      <form
        method="post"
        action="/auth/logout"
      >
        <input
          type="hidden"
          name="_token"
          :value="csrfToken"
        >
        <button type="submit">
          Cerrar sesión
        </button>
      </form>
    </section>
  </main>

  <LoginPage
    v-else
    :error="error"
  />
</template>


<script setup>
import LoginPage from '@/pages/LoginPage.vue';

defineProps({
  user: { type: Object, default: null },
  error: { type: String, default: '' },
  csrfToken: { type: String, required: true },
});
</script>
